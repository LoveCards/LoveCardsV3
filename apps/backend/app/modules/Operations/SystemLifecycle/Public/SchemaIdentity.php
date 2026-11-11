<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Public;

use app\modules\Operations\SystemLifecycle\Contract\Migration;
use app\modules\Operations\SystemLifecycle\Contract\MigrationLedger;
use app\modules\Operations\SystemLifecycle\Contract\MigrationSource;
use app\modules\Operations\SystemLifecycle\Domain\MigrationProgress;
use app\modules\Operations\SystemLifecycle\Contract\UpgradePlanRepository;
use app\modules\Operations\SystemLifecycle\Contract\ReleasePointer;
use app\modules\Operations\SystemLifecycle\Domain\MigrationPhase;
use app\modules\Operations\SystemLifecycle\Domain\MigrationStatus;
use app\modules\Operations\SystemLifecycle\Domain\UpgradeStep;
use app\modules\Operations\SystemLifecycle\Domain\UpgradeStepState;

/** 以当前代码迁移目录和已完成账本共同确定运行时 Schema 身份。 */
final class SchemaIdentity
{
    /** Worker 进程启动后，代码目录作为不可变快照；FPM 请求不会调用 prime。 */
    private static array $workerCatalog = [];

    public function __construct(private MigrationSource $source, private MigrationLedger $ledger,
        private ?UpgradePlanRepository $plans = null, private ?ReleasePointer $pointer = null) {}

    public function current(): string
    {
        return $this->identityForDirectory(dirname(__DIR__, 5) . '/database/migrations', false, true, true);
    }

    public function primeForDirectory(string $directory): string
    {
        return $this->identityForDirectory($directory, false, true);
    }

    /** FPM 探测验证本进程代码目录与账本，但不触发账本建表。 */
    public function probeIdentity(): string
    {
        return $this->identityForDirectory(dirname(__DIR__, 5) . '/database/migrations', true, false, true);
    }

    /** 必须在事件领取事务内调用；不缓存计划、Pointer 或账本资格。 */
    public function assertCanConsume(): void
    {
        $this->identityForDirectory(dirname(__DIR__, 5) . '/database/migrations', true, false, true, true);
    }

    /** 阶段资格验收使用指定进程目录；完整身份接口仍严格 complete-only。 */
    public function runtimeForDirectory(string $directory, string $releaseId): string
    {
        return $this->identityForDirectory($directory, true, false, true, false, $releaseId);
    }

    public function forDirectory(string $directory): string
    {
        return $this->identityForDirectory($directory, false);
    }

    public function forDirectoryReadOnly(string $directory): string
    {
        return $this->identityForDirectory($directory, true);
    }

    private function identityForDirectory(string $directory, bool $readOnly, bool $prime = false,
        bool $runtime = false, bool $lock = false, ?string $releaseId = null): string
    {
        $key = realpath($directory) ?: $directory;
        if ($readOnly && isset(self::$workerCatalog[$key])) {
            $declared = self::$workerCatalog[$key];
        } else {
            $declared = [];
            foreach ($this->source->discover($directory) as $migration) {
                if (!$migration instanceof Migration) throw new \RuntimeException('Migration Catalog 返回无效迁移');
                $descriptor = $migration->descriptor();
                if (isset($declared[$descriptor->id()])) throw new \RuntimeException('Migration Catalog 存在重复 ID');
                $declared[$descriptor->id()] = $descriptor->checksum();
            }
        }
        if ($declared === []) throw new \RuntimeException('Migration Catalog 为空');

        // 先锁计划再读账本，与 Contract 步骤开始/结束的 CAS 串行。
        $plan = $runtime ? ($this->plans ?? app(UpgradePlanRepository::class))->unsettled($lock) : null;
        $rows = $readOnly ? $this->ledger->allReadOnly() : $this->ledger->all();
        $preContract = false;
        if ($runtime) {
            $active = ($this->pointer ?? app(ReleasePointer::class))->activeReleaseId();
            $processRelease = $releaseId ?? (getenv('LC_ACTIVE_RELEASE_ID') ?: 'legacy');
            if ($active !== null && $active !== $processRelease) throw new \RuntimeException('消费者 Release 与活动 Pointer 不一致');
            if ($plan !== null) {
                $current = $plan->plan()->current();
                if ($current?->state() === UpgradeStepState::FAILED
                    && !in_array($current->name(), [UpgradeStep::HEALTH_CHECK, UpgradeStep::FINAL_HEALTH], true)) {
                    throw new \RuntimeException('当前升级步骤失败，消费者资格已撤销');
                }
                if ($current?->name() === UpgradeStep::CONTRACT_MIGRATIONS
                    && in_array($current->state(), [UpgradeStepState::RUNNING, UpgradeStepState::FAILED], true)) {
                    throw new \RuntimeException('Contract 已开始，暂停全部消费者领取');
                }
                $eligibleStep = in_array($current?->name(), [UpgradeStep::RELOAD_RUNTIME, UpgradeStep::HEALTH_CHECK, UpgradeStep::CONTRACT_MIGRATIONS], true);
                $eligibleFailure = $current?->state() !== UpgradeStepState::FAILED || $current?->name() === UpgradeStep::HEALTH_CHECK;
                $expected = $plan->context()['target_migration_checksums'] ?? [];
                ksort($expected, SORT_STRING);
                $catalog = $declared; ksort($catalog, SORT_STRING);
                $preContract = $plan->planVersion() === 2 && $eligibleStep && $eligibleFailure
                    && $active === $plan->plan()->releaseId() && $processRelease === $active
                    && !isset($plan->context()['pointer_restored_at'])
                    && isset($plan->context()['migrations_verified_at']) && $expected === $catalog;
            }
        }
        $applied = $this->records($rows, $preContract);
        ksort($declared, SORT_STRING);
        if ($declared !== $applied) throw new \RuntimeException('Migration Catalog 与账本不一致');

        if ($prime) self::$workerCatalog[$key] = $declared;

        return $this->fingerprint($applied);
    }

    private function records(array $rows, bool $preContract = false): array
    {
        $applied = [];
        foreach ($rows as $progress) {
            $waitingContract = $progress instanceof MigrationProgress && $preContract
                && $progress->phase() === MigrationPhase::CONTRACT
                && $progress->status() === MigrationStatus::WAITING;
            if (!$progress instanceof MigrationProgress || (!$progress->isComplete() && !$waitingContract)) {
                throw new \RuntimeException('Migration Ledger 存在未完成或无效记录');
            }
            if (isset($applied[$progress->id()])) throw new \RuntimeException('Migration Ledger 存在重复 ID');
            $applied[$progress->id()] = $progress->checksum();
        }
        ksort($applied, SORT_STRING);
        if ($applied === []) throw new \RuntimeException('Migration Ledger 为空');
        return $applied;
    }

    private function fingerprint(array $applied): string
    {
        return hash('sha256', json_encode($applied, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
