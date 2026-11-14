<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Application\SystemLifecycleCoordinator;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseMigrationExecutor;

final class SystemLifecycleMigrationExecutor implements ReleaseMigrationExecutor
{
    public function __construct(private SystemLifecycleCoordinator $coordinator) {}

    public function inspectPhase(string $directory, string $phase): ?array
    {
        return $this->coordinator->inspectPhase($directory, $phase);
    }

    public function advancePhase(
        string $directory,
        string $phase,
        bool $allowContract,
        string $lifecycleLeaseHolder,
        bool $retryFailed = false
    ): ?array {
        $rows = $this->coordinator->runPhaseWithLease(
            $directory,
            $phase,
            $allowContract,
            $lifecycleLeaseHolder,
            $retryFailed
        );
        return $rows[0] ?? null;
    }
}
