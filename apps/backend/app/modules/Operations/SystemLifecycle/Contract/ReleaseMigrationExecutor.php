<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

interface ReleaseMigrationExecutor
{
    /** 调用方持有生命周期 Lease 时，读取本阶段下一项的原始身份与进度供执行前审计。 */
    public function inspectPhase(string $directory, string $phase): ?array;

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
        string $lifecycleLeaseHolder,
        bool $retryFailed = false
    ): ?array;
}
