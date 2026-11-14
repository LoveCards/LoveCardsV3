<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Eventing\Contract\ScheduledRunLedger;
use think\facade\Db;

/** MySQL 运行台账；只记录实际一次性消费尝试。 */
final class ThinkDbScheduledRunLedger implements ScheduledRunLedger
{
    public function startRun(string $workerId, string $triggerSource): int
    {
        $now = gmdate('Y-m-d H:i:s');
        return (int) Db::table('scheduler_runs')->insertGetId([
            'worker_id' => $workerId,
            'trigger_source' => $triggerSource,
            'release_id' => (string) (getenv('LC_ACTIVE_RELEASE_ID') ?: ''),
            'started_at' => $now,
            'outcome' => 'running',
            'created_at' => $now,
            'updated_at' => $now,
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
