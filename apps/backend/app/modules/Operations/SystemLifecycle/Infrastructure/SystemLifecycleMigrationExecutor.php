<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Application\SystemLifecycleCoordinator;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseMigrationExecutor;

final class SystemLifecycleMigrationExecutor implements ReleaseMigrationExecutor
{
    public function __construct(private SystemLifecycleCoordinator $coordinator) {}

    public function advancePhase(
        string $directory,
        string $phase,
        bool $allowContract,
        string $lifecycleLeaseHolder
    ): ?array {
        $rows = $this->coordinator->runPhaseWithLease(
            $directory,
            $phase,
            $allowContract,
            $lifecycleLeaseHolder
        );
        return $rows[0] ?? null;
    }
}
