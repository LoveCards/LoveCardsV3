<?php
declare(strict_types=1);

namespace app\command;

use app\modules\Platform\Eventing\Application\RequestExecutor;
use app\modules\Platform\Eventing\Domain\RequestExecutionBudget;
use app\modules\Platform\Eventing\Public\ExecutionMode;
use app\modules\Platform\Eventing\Public\HandlerRegistry;
use app\modules\Operations\Runtime\Contract\SchedulerRunLedger;
use think\console\Command;
use think\console\input\Option;
use think\console\Input;
use think\console\Output;

/** Runs bounded Eventing work for cron/systemd timers when no daemon is enabled. */
final class SchedulerRunOnce extends Command
{
    protected function configure(): void
    {
        $this->setName('lc:scheduler:run-once')->setDescription('执行一次可裁剪运行时调度任务')
            ->addOption('max-jobs', null, Option::VALUE_OPTIONAL, '最大任务数', '20')
            ->addOption('max-ms', null, Option::VALUE_OPTIONAL, '最大执行毫秒数', '1500');
    }

    protected function execute(Input $input, Output $output): int
    {
        $executionModes = [ExecutionMode::REQUEST_SAFE, ExecutionMode::DAEMON_REQUIRED];
        // 运行台账：Scheduler 能力探测（runtime 控制面）以本表为事实源。
        $ledger = app(SchedulerRunLedger::class);
        $runId = $ledger->startRun('scheduler-' . getmypid());
        try {
            $report = app(RequestExecutor::class)->run(new RequestExecutionBudget(
                'scheduler-' . getmypid(),
                max(1, min(20, (int) $input->getOption('max-jobs'))),
                max(1, min(1000, (int) $input->getOption('max-ms'))),
                300,
                app(HandlerRegistry::class)->queueNamesFor($executionModes),
                $executionModes
            ));
        } catch (\Throwable $exception) {
            $ledger->finishRun($runId, 'failed', 0);
            throw $exception;
        }
        $ledger->finishRun($runId, 'ok', (int) $report->claimed());
        $output->writeln('claimed=' . $report->claimed());
        return 0;
    }
}
