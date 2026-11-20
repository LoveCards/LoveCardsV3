<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

use app\modules\Platform\Eventing\Contract\ScheduledRunLedger;
use app\modules\Platform\Eventing\Application\RequestExecutor;
use app\modules\Platform\Eventing\Domain\RequestExecutionBudget;
use think\facade\Db;
use app\modules\Platform\Config\Public\DeploymentNamespace;
use app\modules\Operations\SystemLifecycle\Contract\UpgradePlanRepository;
use app\modules\Operations\SystemLifecycle\Public\UpgradeConsumerFence;

/** CLI cron 与受认证 HTTP cron 共用的唯一一次性消费用例。 */
final class ScheduledEventConsumption
{
    public function run(string $source, int $maxJobs = 20, int $maxMilliseconds = 1000): int
    {
        if (!in_array($source, ['cli', 'http'], true)) {
            throw new EventConsumptionConflict('invalid-trigger', '定时触发源不合法');
        }
        if ($maxJobs < 1 || $maxJobs > 20 || $maxMilliseconds < 1 || $maxMilliseconds > 1000) {
            throw new \InvalidArgumentException('一次性消费预算超出上限');
        }

        // 两个 Host 共用同一 MySQL 命名锁，不依赖 FPM/CLI 进程内状态。
        $lockName = DeploymentNamespace::current()->scheduledLock();
        $connection = Db::connect();
        $lock = $connection->query("SELECT GET_LOCK(?, 0) AS acquired", [$lockName]);
        if ((int) ($lock[0]['acquired'] ?? 0) !== 1) {
            throw new EventConsumptionConflict('already-running', '已有一次性消费者正在运行');
        }
        try {
            $workerId = 'scheduler-' . $source . '-' . getmypid();
            $ledger = app(ScheduledRunLedger::class);
            // 与 Upgrade Plan 的状态切换共用行锁，避免迁移栅栏与新 run 交错。
            Db::startTrans();
            try {
                $plan = app(UpgradePlanRepository::class)->unsettled(true);
                if (UpgradeConsumerFence::blocks($plan)) {
                    // backup_gate 的 RUNNING CAS 已发布栅栏，但成功 run 证明尚未捕获。
                    // 此阶段不写心跳，避免覆盖待捕获的最近一次真实运行。
                    if (UpgradeConsumerFence::capturingProof($plan)) {
                        Db::commit();
                        return 0;
                    }
                    $proof = $plan->context()['event_consumer_proof'] ?? null;
                    $previous = $plan->previousReleaseId();
                    $release = (string) (getenv('LC_ACTIVE_RELEASE_ID') ?: '');
                    if (!is_array($proof) || ($proof['mode'] ?? null) !== 'scheduled'
                        || ($proof['trigger'] ?? null) !== $source
                        || ($proof['previous_release_id'] ?? null) !== $previous
                        || ($previous === null
                            ? !in_array($release, ['', 'legacy'], true)
                            : !hash_equals($previous, $release))) {
                        throw new EventConsumptionConflict('upgrade-pause-unavailable', '升级暂停证明与当前定时入口不匹配');
                    }
                    $pausedId = $ledger->startRun('upgrade-pause-' . $plan->plan()->planId(), $source);
                    $ledger->finishRun($pausedId, 'paused', 0);
                    Db::commit();
                    return 0;
                }
                $state = app(EventConsumption::class)->current();
                if ($state === null || $state['mode'] !== 'scheduled' || $state['state'] !== 'active'
                    || $state['scheduled_trigger'] !== $source) {
                    throw new EventConsumptionConflict('mode-not-selected', '当前未启用该定时消费入口');
                }
                $runId = $ledger->startRun($workerId, $source);
                Db::commit();
            } catch (\Throwable $exception) {
                Db::rollback();
                throw $exception;
            }
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
            $connection->query("SELECT RELEASE_LOCK(?)", [$lockName]);
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
                'run_id' => (int) ($row['run_id'] ?? 0),
                'worker_id' => (string) ($row['worker_id'] ?? ''),
                'trigger_source' => (string) ($row['trigger_source'] ?? ''),
                'release_id' => (string) ($row['release_id'] ?? ''),
                'outcome' => (string) ($row['outcome'] ?? ''),
                'finished_at' => (string) ($row['finished_at'] ?? ''),
                'started_at' => (string) ($row['started_at'] ?? ''),
                'jobs_processed' => (int) ($row['jobs_processed'] ?? 0),
            ];
        } catch (\Throwable $ignored) {
            return null;
        }
    }

    /** 栅栏发布并捕获成功 run 后，任何非本计划暂停心跳都撤销激活资格。 */
    public function hasUnexpectedRunsAfter(int $runId, string $workerId, string $source, string $releaseId): bool
    {
        $rows = Db::query(
            'SELECT COUNT(*) AS total FROM scheduler_runs WHERE run_id > ? '
            . 'AND (outcome <> ? OR worker_id <> ? OR trigger_source <> ? OR release_id <> ?)',
            [$runId, 'paused', $workerId, $source, $releaseId]
        );
        return (int) ($rows[0]['total'] ?? 0) > 0;
    }
}
