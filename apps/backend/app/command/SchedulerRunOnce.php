<?php
declare(strict_types=1);

namespace app\command;

use app\modules\Platform\Eventing\Application\RequestExecutor;
use app\modules\Platform\Eventing\Domain\RequestExecutionBudget;
use think\console\Command;
use think\console\input\Option;
use think\console\Input;
use think\console\Output;

/** Runs request-safe work for cron/systemd timers when no daemon is enabled. */
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
        $report = app(RequestExecutor::class)->run(new RequestExecutionBudget(
            'scheduler-' . getmypid(),
            max(1, min(20, (int) $input->getOption('max-jobs'))),
            max(1, min(1000, (int) $input->getOption('max-ms'))),
            30,
            ['critical', 'default', 'bulk']
        ));
        $output->writeln('claimed=' . $report->claimed());
        return 0;
    }
}
