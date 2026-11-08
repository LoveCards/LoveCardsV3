<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Contract\SchedulerRunLedger;
use think\facade\Db;

/** MySQL 台账实现；时间统一 UTC，与 SchedulerRunProbe 的窗口比较一致。 */
final class ThinkDbSchedulerRunLedger implements SchedulerRunLedger
{
    public function startRun(string $workerId): int
    {
        return (int) Db::table('scheduler_runs')->insertGetId([
            'worker_id' => $workerId,
            'started_at' => gmdate('Y-m-d H:i:s'),
            'outcome' => 'running',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function finishRun(int $runId, string $outcome, int $jobsProcessed): void
    {
        Db::table('scheduler_runs')->where('run_id', $runId)->update([
            'finished_at' => gmdate('Y-m-d H:i:s'),
            'outcome' => $outcome,
            'jobs_processed' => max(0, $jobsProcessed),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
