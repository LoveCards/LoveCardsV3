<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

use app\modules\Platform\Eventing\Contract\ScheduledRunLedger;
use app\modules\Platform\Eventing\Application\RequestExecutor;
use app\modules\Platform\Eventing\Domain\RequestExecutionBudget;
use think\facade\Db;

/** CLI cron 与受认证 HTTP cron 共用的唯一一次性消费用例。 */
final class ScheduledEventConsumption
{
    public function run(string $source, int $maxJobs = 20, int $maxMilliseconds = 1000): int
    {
        if (!in_array($source, ['cli', 'http'], true)) {
            throw new EventConsumptionConflict('invalid-trigger', '定时触发源不合法');
        }
        $state = app(EventConsumption::class)->current();
        if ($state === null || $state['mode'] !== 'scheduled' || $state['state'] !== 'active'
            || $state['scheduled_trigger'] !== $source) {
            throw new EventConsumptionConflict('mode-not-selected', '当前未启用该定时消费入口');
        }
        if ($maxJobs < 1 || $maxJobs > 20 || $maxMilliseconds < 1 || $maxMilliseconds > 1000) {
            throw new \InvalidArgumentException('一次性消费预算超出上限');
        }

        // 两个 Host 共用同一 MySQL 命名锁，不依赖 FPM/CLI 进程内状态。
        $connection = Db::connect();
        $lock = $connection->query("SELECT GET_LOCK('lc:eventing:scheduled', 0) AS acquired");
        if ((int) ($lock[0]['acquired'] ?? 0) !== 1) {
            throw new EventConsumptionConflict('already-running', '已有一次性消费者正在运行');
        }
        try {
            $workerId = 'scheduler-' . $source . '-' . getmypid();
            $ledger = app(ScheduledRunLedger::class);
            $runId = $ledger->startRun($workerId, $source);
            try {
                $executionModes = [ExecutionMode::REQUEST_SAFE, ExecutionMode::DAEMON_REQUIRED];
                $report = app(RequestExecutor::class)->run(new RequestExecutionBudget(
                    $workerId,
                    $maxJobs,
                    $maxMilliseconds,
                    300,
                    app(HandlerRegistry::class)->queueNamesFor($executionModes),
                    $executionModes,
                    'scheduled',
                    $source
                ));
            } catch (\Throwable $exception) {
                $ledger->finishRun($runId, 'failed', 0);
                throw $exception;
            }
            $ledger->finishRun($runId, 'ok', $report->claimed());
            return $report->claimed();
        } finally {
            $connection->query("SELECT RELEASE_LOCK('lc:eventing:scheduled')");
        }
    }

    /** Runtime 只经公开入口读取最近一次触发记录，不能让卡住的 running 隐藏在旧成功后。 */
    public function latestRun(): ?array
    {
        try {
            $row = Db::table('scheduler_runs')
                ->order('run_id', 'desc')
                ->find();
            if (!is_array($row)) return null;
            return [
                'trigger_source' => (string) ($row['trigger_source'] ?? ''),
                'outcome' => (string) ($row['outcome'] ?? ''),
                'finished_at' => (string) ($row['finished_at'] ?? ''),
                'started_at' => (string) ($row['started_at'] ?? ''),
                'jobs_processed' => (int) ($row['jobs_processed'] ?? 0),
            ];
        } catch (\Throwable $ignored) {
            return null;
        }
    }
}
