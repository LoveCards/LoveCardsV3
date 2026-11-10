<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Contract;

/** 一次性消费者运行台账端口。 */
interface ScheduledRunLedger
{
    public function startRun(string $workerId, string $triggerSource): int;

    public function finishRun(int $runId, string $outcome, int $jobsProcessed): void;
}
