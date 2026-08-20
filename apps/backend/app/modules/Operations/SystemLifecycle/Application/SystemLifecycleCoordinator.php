<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Application;

use app\modules\Operations\SystemLifecycle\Contract\MigrationSource;
use app\modules\Operations\SystemLifecycle\Contract\UpgradeLease;
use app\modules\Operations\SystemLifecycle\Contract\UpgradePlanRepository;
use app\modules\Operations\SystemLifecycle\Domain\MigrationPhase;
use app\modules\Operations\SystemLifecycle\Domain\MigrationPlanStep;
use app\modules\Operations\SystemLifecycle\Domain\LifecycleLease;
use app\modules\Operations\SystemLifecycle\Exception\UnsettledUpgradePlanException;
use app\modules\Operations\SystemLifecycle\Exception\UpgradeLeaseUnavailableException;

final class SystemLifecycleCoordinator
{
    public function __construct(
        private MigrationPlanner $planner,
        private MigrationRunner $runner,
        private MigrationSource $source,
        private UpgradeLease $lease,
        private UpgradePlanRepository $upgradePlans
    ) {}

    /** 每次调用全局最多推进一个 phase 或一个 backfill batch。 */
    public function run(string $directory, bool $allowContract = false): array
    {
        $holder = $this->lease->acquire(
            LifecycleLease::NAME,
            $this->now(),
            LifecycleLease::TTL_SECONDS
        );
        if ($holder === null) {
            throw new UpgradeLeaseUnavailableException('已有系统迁移正在执行');
        }

        try {
            $this->assertNoUnsettledUpgradePlan();
            return $this->advanceOne($directory, $allowContract, $holder, null);
        } finally {
            $this->lease->release(LifecycleLease::NAME, $holder);
        }
    }

    /**
     * Release upgrades use a phase fence so an HTTP request cannot cross from
     * one schema phase into the next one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function runPhase(string $directory, string $phase, bool $allowContract = false): array
    {
        $this->assertExecutablePhase($phase, $allowContract);

        $holder = $this->lease->acquire(
            LifecycleLease::NAME,
            $this->now(),
            LifecycleLease::TTL_SECONDS
        );
        if ($holder === null) {
            throw new UpgradeLeaseUnavailableException('已有系统迁移正在执行');
        }

        try {
            return $this->advanceOne($directory, $allowContract, $holder, $phase);
        } finally {
            $this->lease->release(LifecycleLease::NAME, $holder);
        }
    }

    /**
     * UpgradeCoordinator already owns the global lifecycle lease. Reuse and
     * renew that holder instead of attempting a nested acquisition.
     *
     * @return array<int, array<string, mixed>>
     */
    public function runPhaseWithLease(
        string $directory,
        string $phase,
        bool $allowContract,
        string $lifecycleLeaseHolder
    ): array {
        $this->assertExecutablePhase($phase, $allowContract);
        if ($lifecycleLeaseHolder === '') throw new \InvalidArgumentException('Lifecycle Lease holder 不能为空');
        return $this->advanceOne($directory, $allowContract, $lifecycleLeaseHolder, $phase);
    }

    /**
     * 安装和 CLI 批处理使用：持续推进到全部完成，或只剩未获批的 contract。
     *
     * @return array<int, array<string, mixed>>
     */
    public function runUntilSettled(
        string $directory,
        bool $allowContract = false,
        int $maximumAdvances = 10000
    ): array {
        if ($maximumAdvances < 1 || $maximumAdvances > 100000) {
            throw new \InvalidArgumentException('Migration 最大推进次数不合法');
        }
        $holder = $this->lease->acquire(
            LifecycleLease::NAME,
            $this->now(),
            LifecycleLease::TTL_SECONDS
        );
        if ($holder === null) {
            throw new UpgradeLeaseUnavailableException('已有系统迁移正在执行');
        }

        $results = [];
        try {
            $this->assertNoUnsettledUpgradePlan();
            for ($advance = 0; $advance < $maximumAdvances; $advance++) {
                $rows = $this->advanceOne($directory, $allowContract, $holder, null);
                if ($rows === []) return $results;
                array_push($results, ...$rows);
            }
            throw new \RuntimeException('Migration 推进次数超过安全上限');
        } finally {
            $this->lease->release(LifecycleLease::NAME, $holder);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function advanceOne(
        string $directory,
        bool $allowContract,
        string $holder,
        ?string $requiredPhase
    ): array
    {
        if (!$this->lease->renew(LifecycleLease::NAME, $holder, $this->now(), LifecycleLease::TTL_SECONDS)) {
            throw new UpgradeLeaseUnavailableException('系统迁移 Lease 已丢失');
        }
        $migrations = $this->source->discover($directory);
        $plan = $this->planner->plan($migrations);
        foreach ($plan->steps() as $step) {
            if ($step->state() === MigrationPlanStep::COMPLETE) continue;
            $phase = $step->progress()?->phase() ?? MigrationPhase::PREFLIGHT;
            if ($requiredPhase !== null && $phase !== $requiredPhase) continue;
            if ($step->state() === MigrationPlanStep::WAITING && !$allowContract) continue;
            foreach ($migrations as $migration) {
                if ($migration->descriptor()->id() === $step->descriptor()->id()) {
                    $progress = $this->runner->runNext($migration, $allowContract);
                    if (!$this->lease->renew(LifecycleLease::NAME, $holder, $this->now(), LifecycleLease::TTL_SECONDS)) {
                        throw new UpgradeLeaseUnavailableException('系统迁移 Lease 已丢失');
                    }
                    return [$progress->toArray()];
                }
            }
        }
        return [];
    }

    private function assertNoUnsettledUpgradePlan(): void
    {
        if ($this->upgradePlans->existsUnsettled()) {
            throw new UnsettledUpgradePlanException('存在未完成的升级计划，不能并行执行手动迁移');
        }
    }

    private function assertExecutablePhase(string $phase, bool $allowContract): void
    {
        MigrationPhase::assertValid($phase);
        if ($phase === MigrationPhase::COMPLETE) {
            throw new \InvalidArgumentException('complete 不是可执行的 Migration Phase');
        }
        if ($phase === MigrationPhase::CONTRACT && !$allowContract) {
            throw new \LogicException('Contract Migration 必须显式批准');
        }
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
