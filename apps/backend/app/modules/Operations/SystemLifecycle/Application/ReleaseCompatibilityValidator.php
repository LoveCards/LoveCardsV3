<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Application;

use app\modules\Operations\SystemLifecycle\Contract\Migration;
use app\modules\Operations\SystemLifecycle\Contract\MigrationLedger;
use app\modules\Operations\SystemLifecycle\Contract\MigrationSource;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseCompatibility;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseEnvironment;
use app\modules\Operations\SystemLifecycle\Domain\MigrationProgress;
use app\modules\Operations\SystemLifecycle\Domain\MigrationPhase;
use app\modules\Operations\SystemLifecycle\Domain\MigrationStatus;
use app\modules\Operations\SystemLifecycle\Domain\ReleaseManifest;
use app\modules\Operations\SystemLifecycle\Exception\ReleaseVerificationException;
use app\modules\Operations\SystemLifecycle\Domain\HandlerCatalogHasher;
use app\modules\Platform\Eventing\Public\EventConsumption;
use app\modules\Platform\Eventing\Public\ScheduledEventConsumption;
use app\modules\Operations\Runtime\Public\RuntimeCommunication;

final class ReleaseCompatibilityValidator implements ReleaseCompatibility
{
    public function __construct(
        private MigrationSource $migrations,
        private MigrationLedger $ledger,
        private ReleaseEnvironment $environment
    ) {}

    public function verifyEnvironment(ReleaseManifest $manifest): void
    {
        if (version_compare($this->environment->phpVersion(), $manifest->minimumPhp(), '<')) {
            throw new ReleaseVerificationException('当前 PHP 版本低于 Release 最低要求');
        }
        if (version_compare($this->environment->mysqlVersion(), $manifest->minimumMysql(), '<')) {
            throw new ReleaseVerificationException('当前 MySQL 版本低于 Release 最低要求');
        }
        foreach ($manifest->requiredExtensions() as $extension) {
            if (!$this->environment->hasExtension($extension)) {
                throw new ReleaseVerificationException('Release 缺少必需 PHP 扩展: ' . $extension);
            }
        }
    }

    public function verifyStagedRelease(ReleaseManifest $manifest, string $releaseRoot): void
    {
        $manifestPath = rtrim($releaseRoot, '/\\') . DIRECTORY_SEPARATOR . 'release.json';
        $json = is_file($manifestPath) ? file_get_contents($manifestPath) : false;
        if ($json === false) throw new ReleaseVerificationException('Staged Release 缺少 release.json');
        try {
            $stored = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ReleaseVerificationException('Staged Release Manifest 不是有效 JSON', 0, $exception);
        }
        if (!is_array($stored)
            || !hash_equals($manifest->documentChecksum(), ReleaseManifest::fromArray($stored)->documentChecksum())) {
            throw new ReleaseVerificationException('Staged Release Manifest 与已验签 Manifest 不一致');
        }

        $catalog = $this->catalog($releaseRoot);
        if ($catalog['checksums'] !== $manifest->migrationChecksums()) {
            throw new ReleaseVerificationException('Release Migration Catalog 与 Manifest Checksum 不一致');
        }
        $target = $catalog['ids'] === [] ? $this->currentSchema(true) : (string) end($catalog['ids']);
        if ($manifest->targetSchema() !== $target) {
            throw new ReleaseVerificationException('Release target_schema 与 Migration Catalog 不一致');
        }

        $current = $this->currentSchema(true);
        $range = $manifest->sourceSchemaRange();
        if ($this->compareSchema($current, $range['min']) < 0 || $this->compareSchema($current, $range['max']) > 0) {
            throw new ReleaseVerificationException('当前 Schema 不在 Release 支持的升级范围内');
        }
        $this->assertLedgerIsPrefix($manifest);

        $catalogPath = rtrim($releaseRoot, '/\\') . DIRECTORY_SEPARATOR . 'handler-catalog.json';
        try { $catalogHash = HandlerCatalogHasher::hashFile($catalogPath); }
        catch (\Throwable $exception) { throw new ReleaseVerificationException('Release 缺少受校验的 handler-catalog.json', 0, $exception); }
        if (!hash_equals($manifest->handlerCatalogHash(), $catalogHash)) throw new ReleaseVerificationException('Release Handler Catalog Hash 与 Manifest 不一致');
    }

    public function verifyRuntimeHandlerCatalog(ReleaseManifest $manifest): void
    {
        if (!hash_equals($manifest->handlerCatalogHash(), $this->environment->handlerCatalogHash())) {
            throw new ReleaseVerificationException('激活后的运行时 Handler Catalog 与 Release 不一致');
        }
    }

    public function verifyEventConsumptionReady(): void
    {
        (new EventConsumption())->assertUpgradeActivationReady();
    }

    public function verifyEventConsumptionOperational(string $activatedAt, string $releaseId): void
    {
        $state = (new EventConsumption())->current();
        if ($state === null || $state['state'] !== 'active') {
            throw new ReleaseVerificationException('激活后事件处理策略未就绪');
        }
        $runtime = (new RuntimeCommunication())->snapshot();
        $capabilities = [];
        foreach ($runtime['capabilities'] ?? [] as $entry) $capabilities[$entry['key']] = $entry;
        if ($state['mode'] === 'resident') {
            if (!(new RuntimeCommunication())->eventWorkerReadyForRelease($releaseId)) {
                throw new ReleaseVerificationException('激活后 Event Worker 未就绪，请重启并验证新 Release Worker');
            }
        } else {
            $latest = (new ScheduledEventConsumption())->latestRun();
            try {
                $finished = is_array($latest) && $latest['finished_at'] !== ''
                    ? new \DateTimeImmutable((string) $latest['finished_at'], new \DateTimeZone('UTC')) : null;
                $activated = $activatedAt !== '' ? new \DateTimeImmutable($activatedAt) : null;
            } catch (\Throwable $ignored) {
                $finished = null;
                $activated = null;
            }
            if (($capabilities['scheduler']['ready'] ?? false) !== true
                || !is_array($latest)
                || $latest['trigger_source'] !== $state['scheduled_trigger']
                || $latest['outcome'] !== 'ok'
                || $finished === null || $activated === null || $finished < $activated) {
                throw new ReleaseVerificationException('激活后尚无当前 Release 的成功定时消费，请等待 cron 执行并重试健康检查');
            }
        }
        if ($runtime['desired_mode'] === 'realtime' && $runtime['status'] !== 'ready') {
            throw new ReleaseVerificationException('激活后实时通信链路未就绪');
        }
    }

    public function requiresContract(ReleaseManifest $manifest, string $releaseRoot): bool
    {
        $catalog = $this->catalog($releaseRoot);
        if ($catalog['checksums'] !== $manifest->migrationChecksums()) {
            throw new ReleaseVerificationException('Release Migration Catalog 与 Manifest Checksum 不一致');
        }
        return $catalog['has_contract'];
    }

    public function verifyPreContractMigrationsComplete(ReleaseManifest $manifest): void
    {
        $records = $this->migrationRecords($manifest);
        foreach ($manifest->migrationChecksums() as $id => $checksum) {
            $record = $records[$id] ?? null;
            $settled = $record !== null && (
                $record->isComplete()
                || ($record->phase() === MigrationPhase::CONTRACT
                    && $record->status() === MigrationStatus::WAITING)
            );
            if (!$settled || !hash_equals($checksum, $record->checksum())) {
                throw new ReleaseVerificationException('Release Migration 尚未完成 pre-contract 阶段: ' . $id);
            }
        }
    }

    public function verifyMigrationsComplete(ReleaseManifest $manifest): void
    {
        $records = $this->migrationRecords($manifest);
        foreach ($manifest->migrationChecksums() as $id => $checksum) {
            $record = $records[$id] ?? null;
            if ($record === null || !$record->isComplete() || !hash_equals($checksum, $record->checksum())) {
                throw new ReleaseVerificationException('Release Migration 尚未完整应用: ' . $id);
            }
        }
        if ($this->currentSchema(true) !== $manifest->targetSchema()) {
            throw new ReleaseVerificationException('Migration 完成后 Schema 未达到 target_schema');
        }
    }

    /** @return array<string, MigrationProgress> */
    private function migrationRecords(ReleaseManifest $manifest): array
    {
        $records = [];
        foreach ($this->ledger->all() as $record) {
            if (!$record instanceof MigrationProgress) {
                throw new ReleaseVerificationException('Migration Ledger 返回无效记录');
            }
            $records[$record->id()] = $record;
        }
        if (array_diff(array_keys($records), array_keys($manifest->migrationChecksums())) !== []) {
            throw new ReleaseVerificationException('Migration Ledger 存在 Release 未声明记录');
        }
        return $records;
    }

    /** @return array{ids:string[],checksums:array<string,string>,has_contract:bool} */
    private function catalog(string $releaseRoot): array
    {
        $directory = rtrim($releaseRoot, '/\\') . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        $checksums = [];
        $hasContract = false;
        foreach ($this->migrations->discover($directory) as $migration) {
            if (!$migration instanceof Migration) throw new ReleaseVerificationException('Migration Catalog 包含无效对象');
            $descriptor = $migration->descriptor();
            if (isset($checksums[$descriptor->id()])) {
                throw new ReleaseVerificationException('Migration Catalog 包含重复 ID: ' . $descriptor->id());
            }
            $checksums[$descriptor->id()] = $descriptor->checksum();
            $hasContract = $hasContract || $descriptor->hasContract();
        }
        ksort($checksums, SORT_STRING);
        return ['ids' => array_keys($checksums), 'checksums' => $checksums, 'has_contract' => $hasContract];
    }

    private function assertLedgerIsPrefix(ReleaseManifest $manifest): void
    {
        $declared = $manifest->migrationChecksums();
        foreach ($this->ledger->all() as $record) {
            if (!$record instanceof MigrationProgress || !$record->isComplete()) {
                throw new ReleaseVerificationException('当前 Migration Ledger 存在未完成步骤');
            }
            if (!isset($declared[$record->id()]) || !hash_equals($declared[$record->id()], $record->checksum())) {
                throw new ReleaseVerificationException('当前 Migration Ledger 与 Release 不兼容: ' . $record->id());
            }
        }
    }

    private function currentSchema(bool $requireComplete): string
    {
        $ids = [];
        foreach ($this->ledger->all() as $record) {
            if (!$record instanceof MigrationProgress) throw new ReleaseVerificationException('Migration Ledger 返回无效记录');
            if ($requireComplete && !$record->isComplete()) {
                throw new ReleaseVerificationException('当前 Migration Ledger 存在未完成步骤: ' . $record->id());
            }
            if ($record->isComplete()) $ids[] = $record->id();
        }
        if ($ids === []) return '0';
        sort($ids, SORT_STRING);
        return (string) end($ids);
    }

    private function compareSchema(string $left, string $right): int
    {
        if ($left === $right) return 0;
        if ($left === '0') return -1;
        if ($right === '0') return 1;
        return strcmp($left, $right);
    }
}
