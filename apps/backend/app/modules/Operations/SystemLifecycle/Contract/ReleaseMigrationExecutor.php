<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

interface ReleaseMigrationExecutor
{
    /**
     * Advance at most one migration work unit in the requested phase.
     *
     * A null result means that no migration remains in that phase. Contract
     * work must never be requested without explicit approval from the outer
     * upgrade plan.
     *
     * @return array<string, mixed>|null
     */
    public function advancePhase(
        string $directory,
        string $phase,
        bool $allowContract,
        string $lifecycleLeaseHolder
    ): ?array;
}
