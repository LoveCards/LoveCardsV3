<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Application;

use app\modules\Operations\SystemLifecycle\Contract\ReleaseCompatibility;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseMigrationExecutor;
use app\modules\Operations\SystemLifecycle\Contract\ReleasePackageImporter;
use app\modules\Operations\SystemLifecycle\Contract\ReleasePackageStore;
use app\modules\Operations\SystemLifecycle\Contract\ReleasePointer;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseRepository;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseRuntime;
use app\modules\Operations\SystemLifecycle\Contract\SystemBackupRepository;
use app\modules\Operations\SystemLifecycle\Contract\UpgradeBackupGate;
use app\modules\Operations\SystemLifecycle\Contract\UpgradeLease;
use app\modules\Operations\SystemLifecycle\Contract\UpgradePlanRepository;
use app\modules\Operations\SystemLifecycle\Domain\ReleaseManifest;
use app\modules\Operations\SystemLifecycle\Domain\LifecycleLease;
use app\modules\Operations\SystemLifecycle\Domain\MigrationPhase;
use app\modules\Operations\SystemLifecycle\Domain\StoredReleasePackage;
use app\modules\Operations\SystemLifecycle\Domain\StoredUpgradePlan;
use app\modules\Operations\SystemLifecycle\Domain\UpgradePlan;
use app\modules\Operations\SystemLifecycle\Domain\UpgradePlanStep;
use app\modules\Operations\SystemLifecycle\Domain\UpgradeStep;
use app\modules\Operations\SystemLifecycle\Domain\UpgradeStepState;
use app\modules\Operations\SystemLifecycle\Exception\UpgradeLeaseUnavailableException;
use app\shared\SystemPaths\SystemPaths;

/**
 * Durable, one-step-at-a-time Release upgrade coordinator.
 *
 * Every side effect is followed by a CAS checkpoint. If PHP-FPM is killed
 * between the side effect and the checkpoint, the same step is retried
 * idempotently from its persisted RUNNING state.
 */
final class UpgradeCoordinator
{
    public function __construct(
        private UpgradePlanRepository $plans,
        private UpgradeLease $lease,
        private ReleasePackageStore $packages,
        private ReleasePackageImporter $importer,
        private ReleaseRepository $releases,
        private ReleaseCompatibility $compatibility,
        private UpgradeBackupGate $backups,
        private SystemBackupRepository $backupRepository,
        private ReleaseMigrationExecutor $migrations,
        private ReleasePointer $pointer,
        private ReleaseRuntime $runtime,
        private SystemPaths $paths
    ) {}

    public function createPlan(string $sourceZip, ?int $actorId = null): StoredUpgradePlan
    {
        $holder = $this->lease->acquire(LifecycleLease::NAME, $this->now(), LifecycleLease::TTL_SECONDS);
        if ($holder === null) throw new UpgradeLeaseUnavailableException('已有系统升级正在执行');
        $planId = 'upgrade-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(8));
        try {
            if ($this->plans->existsUnsettled()) {
                throw new \LogicException('存在未完成的 Upgrade Plan，不能创建并行升级计划');
            }
            $package = $this->packages->store($planId, $sourceZip);
            try {
                $manifest = $this->importer->inspect($package->path());
                if (!$this->lease->renew(LifecycleLease::NAME, $holder, $this->now(), LifecycleLease::TTL_SECONDS)) {
                    throw new UpgradeLeaseUnavailableException('系统升级 Lease 已丢失');
                }
                $previous = $this->pointer->activeReleaseId();
                $plan = UpgradePlan::create($planId, $manifest->releaseId());
                $stored = new StoredUpgradePlan(
                    $plan,
                    $previous,
                    false,
                    [
                        'package' => $package->toArray(),
                        'manifest_checksum' => $manifest->documentChecksum(),
                        'target_version' => $manifest->version(),
                        'update_notes' => $manifest->releaseNotes(),
                        'created_at' => gmdate('c'),
                    ],
                    $actorId
                );
                return $this->plans->create($stored);
            } catch (\Throwable $exception) {
                try { $this->packages->remove($planId); } catch (\Throwable $ignored) {}
                throw $exception;
            }
        } finally {
            $this->lease->release(LifecycleLease::NAME, $holder);
        }
    }

    public function find(string $planId): ?StoredUpgradePlan
    {
        return $this->plans->find($planId);
    }

    /** @return StoredUpgradePlan[] */
    public function recent(int $limit = 50): array
    {
        return $this->plans->recent($limit);
    }

    /**
     * Manual schema work is not allowed while a Release plan is unresolved.
     * This closes the gap between two HTTP upgrade advances, when no lease is
     * held but the plan may already have changed the expected schema path.
     */
    public function hasUnsettledPlan(): bool
    {
        return $this->plans->existsUnsettled();
    }

    public function approveContract(string $planId): StoredUpgradePlan
    {
        $holder = $this->lease->acquire(LifecycleLease::NAME, $this->now(), LifecycleLease::TTL_SECONDS);
        if ($holder === null) throw new UpgradeLeaseUnavailableException('已有系统升级正在执行');
        try {
            $stored = $this->requiredPlan($planId);
            $this->assertExclusivePlan($planId);
            $this->assertCurrentPlanShape($stored);
            if ($stored->status() === StoredUpgradePlan::RECOVERED) {
                throw new \LogicException('已恢复的 Upgrade Plan 不能批准 Contract');
            }
            $current = $stored->plan()->current();
            if ($stored->contractApproved()
                || $current === null
                || $current->name() !== UpgradeStep::CONTRACT_MIGRATIONS
                || $current->state() !== UpgradeStepState::WAITING) {
                throw new \LogicException('只有正在等待 Contract 审批的 Upgrade Plan 可以批准');
            }
            $manifest = $this->manifest($stored);
            $releaseRoot = $this->paths->releaseRoot($stored->plan()->releaseId());
            if (!$this->compatibility->requiresContract($manifest, $releaseRoot)) {
                throw new \LogicException('当前 Release 没有需要批准的 Contract Migration');
            }
            return $this->plans->save($stored->approveContract(), $stored->plan()->revision());
        } finally {
            $this->lease->release(LifecycleLease::NAME, $holder);
        }
    }

    public function advance(string $planId): StoredUpgradePlan
    {
        $holder = $this->lease->acquire(LifecycleLease::NAME, $this->now(), LifecycleLease::TTL_SECONDS);
        if ($holder === null) throw new UpgradeLeaseUnavailableException('已有系统升级正在执行');
        try {
            $stored = $this->requiredPlan($planId);
            $this->assertExclusivePlan($planId);
            $this->assertCurrentPlanShape($stored);
            if ($stored->status() === StoredUpgradePlan::RECOVERED) {
                throw new \LogicException('已恢复的 Upgrade Plan 不能继续推进');
            }
            $current = $stored->plan()->current();
            if ($current === null) return $stored;
            if ($current->state() === UpgradeStepState::WAITING && !$stored->contractApproved()) return $stored;

            $manifest = $this->manifest($stored);
            $releaseRoot = $this->paths->releaseRoot($stored->plan()->releaseId());

            if ($this->needsContractApproval($current, $stored, $manifest, $releaseRoot)) {
                return $this->plans->save(
                    $stored->withPlan($stored->plan()->waitForApproval($current->name())),
                    $stored->plan()->revision()
                );
            }

            if ($this->isOptionalStep($current, $manifest, $releaseRoot)) {
                $skipped = $stored->withPlan($stored->plan()->skip($current->name()));
                return $this->plans->save($skipped, $stored->plan()->revision());
            }

            $started = $this->plans->save(
                $stored->withPlan($stored->plan()->start($current->name())),
                $stored->plan()->revision()
            );
            if (!$this->lease->renew(LifecycleLease::NAME, $holder, $this->now(), LifecycleLease::TTL_SECONDS)) {
                throw new UpgradeLeaseUnavailableException('系统升级 Lease 已丢失');
            }
            try {
                [$contextPatch, $settled, $cursor] = $this->executeStep(
                    $started,
                    $current->name(),
                    $manifest,
                    $releaseRoot,
                    $holder
                );
                if (!$this->lease->renew(LifecycleLease::NAME, $holder, $this->now(), LifecycleLease::TTL_SECONDS)) {
                    throw new UpgradeLeaseUnavailableException('系统升级 Lease 已丢失');
                }
                $context = array_replace($started->context(), $contextPatch);
                $nextPlan = $settled
                    ? $started->plan()->succeed($current->name())
                    : $started->plan()->checkpoint($current->name(), $cursor);
                return $this->plans->save(
                    $started->withContext($context)->withPlan($nextPlan),
                    $started->plan()->revision()
                );
            } catch (\Throwable $exception) {
                $failedPlan = $started->withPlan(
                    $started->plan()->fail($current->name(), $this->failureCode($exception))
                );
                try { $this->plans->save($started->withContext($started->context())->withPlan($failedPlan->plan()), $started->plan()->revision()); } catch (\Throwable $ignored) {}
                throw $exception;
            }
        } finally {
            $this->lease->release(LifecycleLease::NAME, $holder);
        }
    }

    public function runUntilSettled(string $planId, bool $allowContract = false, int $maximumAdvances = 1000): StoredUpgradePlan
    {
        if ($maximumAdvances < 1 || $maximumAdvances > 10000) throw new \InvalidArgumentException('升级最大推进次数不合法');
        $stored = $this->requiredPlan($planId);
        for ($index = 0; $index < $maximumAdvances; $index++) {
            if (in_array($stored->status(), [UpgradePlan::COMPLETED, StoredUpgradePlan::RECOVERED], true)) return $stored;
            if ($stored->plan()->status() === UpgradePlan::WAITING && !$stored->contractApproved()) {
                if (!$allowContract) return $stored;
                $stored = $this->approveContract($planId);
            }
            $revision = $stored->plan()->revision();
            $stored = $this->advance($planId);
            if ($stored->plan()->revision() === $revision) return $stored;
        }
        throw new \RuntimeException('升级推进次数超过安全上限');
    }

    public function recover(string $planId): StoredUpgradePlan
    {
        $holder = $this->lease->acquire(LifecycleLease::NAME, $this->now(), LifecycleLease::TTL_SECONDS);
        if ($holder === null) throw new UpgradeLeaseUnavailableException('已有系统升级正在执行');
        try {
            $stored = $this->requiredPlan($planId);
            $this->assertExclusivePlan($planId);
            $this->assertCurrentPlanShape($stored);
            if ($stored->status() === StoredUpgradePlan::RECOVERED) {
                throw new \LogicException('Upgrade Plan 已恢复，不能重复执行');
            }
            if (!isset($stored->context()['activated_at'])) {
                throw new \LogicException('Release 尚未激活，不能执行指针恢复');
            }
            if ($stored->previousReleaseId() === null) {
                throw new \LogicException('Upgrade Plan 没有可恢复的旧 Release');
            }
            $contract = $this->requiredStep($stored, UpgradeStep::CONTRACT_MIGRATIONS);
            if (isset($stored->context()['contract_executed_at'])
                || in_array($contract->state(), [UpgradeStepState::RUNNING, UpgradeStepState::FAILED, UpgradeStepState::SUCCEEDED], true)) {
                throw new \LogicException('Contract Migration 已开始，禁止仅恢复 Release 指针');
            }
            $activeReleaseId = $this->pointer->activeReleaseId();
            if (!in_array($activeReleaseId, [$stored->plan()->releaseId(), $stored->previousReleaseId()], true)) {
                throw new \LogicException('当前活动 Release 与 Upgrade Plan 不一致');
            }
            $backup = $this->backups->pointerBackup($planId);
            $restored = $activeReleaseId === $stored->previousReleaseId()
                ? $activeReleaseId
                : $this->pointer->restore((string) $backup['location'], (string) $backup['checksum']);
            if ($restored !== $stored->previousReleaseId()) {
                throw new \RuntimeException('指针备份与 Upgrade Plan 的旧 Release 不一致');
            }
            $this->releases->rollback($stored->plan()->releaseId(), $restored);
            if ($this->runtime->canReload()) $this->runtime->reload($restored);
            $this->runtime->healthCheck($restored);
            $this->backupRepository->markRestored((string) $backup['backup_id']);
            $context = array_replace($stored->context(), [
                'recovered_at' => gmdate('c'),
                'restored_release_id' => $restored,
            ]);
            return $this->plans->save($stored->withContext($context), $stored->plan()->revision());
        } finally {
            $this->lease->release(LifecycleLease::NAME, $holder);
        }
    }

    /** @return array{array<string, mixed>, bool, ?string} */
    private function executeStep(
        StoredUpgradePlan $stored,
        string $step,
        ReleaseManifest $manifest,
        string $releaseRoot,
        string $lifecycleLeaseHolder
    ): array {
        $package = $this->package($stored);
        return match ($step) {
            UpgradeStep::ENVIRONMENT_PROBE => [$this->environmentProbe($manifest), true, null],
            UpgradeStep::IMPORT_RELEASE => [['package_imported' => true, 'package_checksum' => $package->checksum()], true, null],
            UpgradeStep::VERIFY_RELEASE => [$this->verifyRelease($package, $manifest), true, null],
            UpgradeStep::STAGE_RELEASE => [$this->stageRelease($package, $manifest), true, null],
            UpgradeStep::SCHEMA_CHECK => [$this->schemaCheck($manifest, $releaseRoot), true, null],
            UpgradeStep::PREFLIGHT_MIGRATIONS => $this->advanceMigrationPhase($stored, $manifest, $releaseRoot, MigrationPhase::PREFLIGHT, $lifecycleLeaseHolder),
            UpgradeStep::EXPAND_MIGRATIONS => $this->advanceMigrationPhase($stored, $manifest, $releaseRoot, MigrationPhase::EXPAND, $lifecycleLeaseHolder),
            UpgradeStep::BACKFILL => $this->advanceMigrationPhase($stored, $manifest, $releaseRoot, MigrationPhase::BACKFILL, $lifecycleLeaseHolder),
            UpgradeStep::VERIFY_MIGRATIONS => $this->advanceMigrationPhase($stored, $manifest, $releaseRoot, MigrationPhase::VERIFY, $lifecycleLeaseHolder),
            UpgradeStep::BACKUP_GATE => [$this->backupGate($stored), true, null],
            UpgradeStep::DRAIN_RUNTIME => [$this->drain($stored->plan()->releaseId()), true, null],
            UpgradeStep::ACTIVATE_RELEASE => [$this->activate($stored), true, null],
            UpgradeStep::RELOAD_RUNTIME => [$this->reload($stored->plan()->releaseId()), true, null],
            UpgradeStep::HEALTH_CHECK => [$this->health($stored->plan()->releaseId(), $manifest), true, null],
            UpgradeStep::CONTRACT_MIGRATIONS => $this->advanceMigrationPhase($stored, $manifest, $releaseRoot, MigrationPhase::CONTRACT, $lifecycleLeaseHolder),
            default => throw new \LogicException('未知 Upgrade Step: ' . $step),
        };
    }

    private function environmentProbe(ReleaseManifest $manifest): array
    {
        $this->compatibility->verifyEnvironment($manifest);
        return ['environment_verified_at' => gmdate('c')];
    }

    /** @return array<string, mixed> */
    private function verifyRelease(StoredReleasePackage $package, ReleaseManifest $manifest): array
    {
        $verified = $this->importer->verify($package->path(), $this->releases->sequenceFloor($manifest->releaseId()), null, $package->checksum());
        if ($verified->manifest()->releaseId() !== $manifest->releaseId()) throw new \RuntimeException('Release Manifest 在校验期间发生变化');
        return ['verified_manifest_checksum' => $verified->manifest()->documentChecksum(), 'verified_at' => gmdate('c')];
    }

    /** @return array<string, mixed> */
    private function stageRelease(StoredReleasePackage $package, ReleaseManifest $manifest): array
    {
        $verified = $this->importer->import($package->path(), $this->releases->sequenceFloor($manifest->releaseId()), null, $package->checksum());
        $this->releases->stage($verified);
        return ['staged_release_id' => $verified->manifest()->releaseId(), 'staged_at' => gmdate('c')];
    }

    /** @return array<string, mixed> */
    private function schemaCheck(ReleaseManifest $manifest, string $releaseRoot): array
    {
        $this->compatibility->verifyStagedRelease($manifest, $releaseRoot);
        return ['schema_checked_at' => gmdate('c')];
    }

    /** @return array<string, mixed> */
    private function backupGate(StoredUpgradePlan $stored): array
    {
        $rows = $this->backups->prepare($stored->plan()->planId(), $stored->createdBy());
        $this->backups->assertReady($stored->plan()->planId());
        return ['backups' => array_map(static fn(array $row): array => [
            'backup_id' => $row['backup_id'] ?? null,
            'backup_type' => $row['backup_type'] ?? null,
            'status' => $row['status'] ?? null,
        ], $rows), 'backup_gate_at' => gmdate('c')];
    }

    /** @return array{array<string, mixed>, bool, ?string} */
    private function advanceMigrationPhase(
        StoredUpgradePlan $stored,
        ReleaseManifest $manifest,
        string $releaseRoot,
        string $phase,
        string $lifecycleLeaseHolder
    ): array
    {
        $directory = rtrim($releaseRoot, '/\\') . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        $allowContract = $phase === MigrationPhase::CONTRACT;
        if ($allowContract && !$stored->contractApproved()) {
            throw new \LogicException('Contract Migration 尚未获准执行');
        }
        $row = $this->migrations->advancePhase(
            $directory,
            $phase,
            $allowContract,
            $lifecycleLeaseHolder
        );
        $prefix = str_replace('-', '_', $phase);
        if ($row === null) {
            if ($phase === MigrationPhase::VERIFY) {
                $this->compatibility->verifyPreContractMigrationsComplete($manifest);
                return [['migrations_verified_at' => gmdate('c')], true, null];
            }
            if ($phase === MigrationPhase::CONTRACT) {
                $this->compatibility->verifyMigrationsComplete($manifest);
                return [['contract_migrations_completed_at' => gmdate('c')], true, null];
            }
            return [["{$prefix}_migrations_completed_at" => gmdate('c')], true, null];
        }

        $countKey = "{$prefix}_migration_advances";
        $patch = [
            $countKey => (int) ($stored->context()[$countKey] ?? 0) + 1,
            'last_migration_advance' => $row,
        ];
        if ($phase === MigrationPhase::CONTRACT) $patch['contract_executed_at'] = gmdate('c');
        return [$patch, false, $this->migrationCursor($row)];
    }

    /** @return array<string, mixed> */
    private function drain(string $releaseId): array
    {
        if ($this->runtime->canDrain()) $this->runtime->drain($releaseId);
        return ['runtime_drained' => $this->runtime->canDrain()];
    }

    /** @return array<string, mixed> */
    private function activate(StoredUpgradePlan $stored): array
    {
        $this->backups->assertReady($stored->plan()->planId());
        $previous = $stored->previousReleaseId();
        $releaseId = $stored->plan()->releaseId();
        $this->pointer->activate($releaseId);
        try {
            $this->releases->activate($releaseId, $previous);
        } catch (\Throwable $exception) {
            try {
                $backup = $this->backups->pointerBackup($stored->plan()->planId());
                $this->pointer->restore((string) $backup['location'], (string) $backup['checksum']);
            } catch (\Throwable $ignored) {}
            throw $exception;
        }
        return ['active_release_id' => $releaseId, 'activated_at' => gmdate('c')];
    }

    /** @return array<string, mixed> */
    private function reload(string $releaseId): array
    {
        if ($this->runtime->canReload()) $this->runtime->reload($releaseId);
        return ['runtime_reloaded' => $this->runtime->canReload()];
    }

    /** @return array<string, mixed> */
    private function health(string $releaseId, ReleaseManifest $manifest): array
    {
        if ($this->pointer->activeReleaseId() !== $releaseId) throw new \RuntimeException('active Release 指针与升级目标不一致');
        $this->compatibility->verifyRuntimeHandlerCatalog($manifest);
        $this->runtime->healthCheck($releaseId);
        return ['health_checked_at' => gmdate('c')];
    }

    private function needsContractApproval(
        UpgradePlanStep $step,
        StoredUpgradePlan $stored,
        ReleaseManifest $manifest,
        string $releaseRoot
    ): bool {
        return $step->name() === UpgradeStep::CONTRACT_MIGRATIONS
            && !$stored->contractApproved()
            && $this->compatibility->requiresContract($manifest, $releaseRoot);
    }

    private function isOptionalStep(
        UpgradePlanStep $step,
        ReleaseManifest $manifest,
        string $releaseRoot
    ): bool {
        return ($step->name() === UpgradeStep::DRAIN_RUNTIME && !$this->runtime->canDrain())
            || ($step->name() === UpgradeStep::RELOAD_RUNTIME && !$this->runtime->canReload())
            || ($step->name() === UpgradeStep::CONTRACT_MIGRATIONS
                && !$this->compatibility->requiresContract($manifest, $releaseRoot));
    }

    /** @param array<string, mixed> $row */
    private function migrationCursor(array $row): string
    {
        $parts = [
            (string) ($row['id'] ?? 'migration'),
            (string) ($row['phase'] ?? 'unknown'),
        ];
        if (isset($row['cursor']) && $row['cursor'] !== '') $parts[] = (string) $row['cursor'];
        return substr(implode(':', $parts), 0, 2048);
    }

    private function requiredStep(StoredUpgradePlan $stored, string $name): UpgradePlanStep
    {
        foreach ($stored->plan()->steps() as $step) {
            if ($step->name() === $name) return $step;
        }
        throw new \LogicException('Upgrade Plan 缺少安全步骤: ' . $name);
    }

    private function assertCurrentPlanShape(StoredUpgradePlan $stored): void
    {
        $actual = array_map(static fn(UpgradePlanStep $step): string => $step->name(), $stored->plan()->steps());
        if ($actual !== UpgradeStep::ordered()) {
            throw new \LogicException('旧版 Upgrade Plan 不符合当前安全状态机，请重新创建升级计划');
        }
    }

    private function manifest(StoredUpgradePlan $stored): ReleaseManifest
    {
        $package = $this->package($stored);
        $manifest = $this->importer->inspect($package->path());
        $expected = (string) ($stored->context()['manifest_checksum'] ?? '');
        if ($expected === '' || !hash_equals($expected, $manifest->documentChecksum())) {
            throw new \RuntimeException('Upgrade Plan Package Manifest 与初始记录不一致');
        }
        if ($manifest->releaseId() !== $stored->plan()->releaseId()) throw new \RuntimeException('Upgrade Plan Release ID 不一致');
        return $manifest;
    }

    private function package(StoredUpgradePlan $stored): StoredReleasePackage
    {
        $package = StoredReleasePackage::restore((array) ($stored->context()['package'] ?? []));
        $path = realpath($package->path());
        $root = realpath($this->paths->upgradeRoot());
        if ($path === false || $root === false || !$this->isWithin($path, $root)) throw new \RuntimeException('Upgrade Plan Package 路径越界');
        $actual = hash_file('sha256', $package->path());
        if ($actual === false || !hash_equals($package->checksum(), $actual)) throw new \RuntimeException('Upgrade Plan Package Checksum 不匹配');
        return $package;
    }

    private function requiredPlan(string $planId): StoredUpgradePlan
    {
        $stored = $this->plans->find($planId);
        if ($stored === null) throw new \InvalidArgumentException('Upgrade Plan 不存在');
        return $stored;
    }

    private function assertExclusivePlan(string $planId): void
    {
        if ($this->plans->existsUnsettled($planId)) {
            throw new \LogicException('存在其他未完成的 Upgrade Plan，不能交错推进系统升级');
        }
    }

    private function failureCode(\Throwable $exception): string
    {
        $class = substr(strrchr('\\' . get_class($exception), '\\') ?: get_class($exception), 1);
        return substr(strtolower(preg_replace('/[^a-z0-9_.-]+/i', '_', $class) ?: 'upgrade_failure'), 0, 128);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private function isWithin(string $candidate, string $root): bool
    {
        $candidate = str_replace('\\', '/', rtrim($candidate, '/\\'));
        $root = str_replace('\\', '/', rtrim($root, '/\\'));
        if (DIRECTORY_SEPARATOR === '\\') { $candidate = strtolower($candidate); $root = strtolower($root); }
        return $candidate === $root || strncmp($candidate, $root . '/', strlen($root) + 1) === 0;
    }
}
