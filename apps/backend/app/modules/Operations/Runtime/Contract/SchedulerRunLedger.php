<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Contract;

/** Scheduler 单次运行台账端口：能力探测的事实源。 */
interface SchedulerRunLedger
{
    public function startRun(string $workerId): int;

    public function finishRun(int $runId, string $outcome, int $jobsProcessed): void;
}
