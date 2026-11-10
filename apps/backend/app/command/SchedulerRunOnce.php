<?php
declare(strict_types=1);

namespace app\command;

use app\modules\Platform\Eventing\Public\ScheduledEventConsumption;
use think\console\Command;
use think\console\input\Option;
use think\console\Input;
use think\console\Output;

/** CLI cron 的薄适配器；领取与台账由共享用例负责。 */
final class SchedulerRunOnce extends Command
{
    protected function configure(): void
    {
        $this->setName('lc:scheduler:run-once')->setDescription('执行一次可裁剪运行时调度任务')
            ->addOption('max-jobs', null, Option::VALUE_OPTIONAL, '最大任务数', '20')
            ->addOption('max-ms', null, Option::VALUE_OPTIONAL, '最大执行毫秒数', '1000');
    }

    protected function execute(Input $input, Output $output): int
    {
        $claimed = app(ScheduledEventConsumption::class)->run(
            'cli',
            max(1, min(20, (int) $input->getOption('max-jobs'))),
            max(1, min(1000, (int) $input->getOption('max-ms')))
        );
        $output->writeln('claimed=' . $claimed);
        return 0;
    }
}
