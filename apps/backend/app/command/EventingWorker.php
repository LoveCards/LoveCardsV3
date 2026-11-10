<?php
declare(strict_types=1);

namespace app\command;

use app\modules\Operations\SystemLifecycle\Public\SchemaIdentity;
use app\modules\Platform\Eventing\Application\WorkerLoop;
use app\modules\Platform\Eventing\Domain\WorkerOptions;
use app\modules\Platform\Eventing\Public\ExecutionMode;
use app\modules\Platform\Eventing\Public\HandlerRegistry;
use think\console\Command;
use think\console\input\Option;
use think\console\Input;
use think\console\Output;

final class EventingWorker extends Command
{
    protected function configure(): void
    {
        $this->setName('lc:eventing:work')
            ->setDescription('运行 LoveCards Outbox Event Worker')
            ->addOption('max-jobs', null, Option::VALUE_OPTIONAL, '最大任务数', '0');
    }

    protected function execute(Input $input, Output $output): int
    {
        $releaseId = getenv('LC_ACTIVE_RELEASE_ID');
        $releaseId = is_string($releaseId) && $releaseId !== '' ? $releaseId : 'legacy';
        $queues = app(HandlerRegistry::class)->queueNamesFor([ExecutionMode::REQUEST_SAFE, ExecutionMode::DAEMON_REQUIRED]);
        // 启动快照不可随数据库迁移自动更新；新 Schema 必须重启 Worker 后重新验证。
        $schemaIdentity = app(SchemaIdentity::class)->current();
        $options = new WorkerOptions(
            'eventing-' . getmypid(), $queues, 300, 1000, 10000,
            (int) $input->getOption('max-jobs'), 0, $releaseId, $schemaIdentity
        );
        $report = app(WorkerLoop::class)->run($options, static fn(): bool => false);
        $output->writeln('claimed=' . $report->claimed());
        return 0;
    }
}
