<?php
declare(strict_types=1);

namespace app\command;

use app\modules\Operations\SystemLifecycle\Application\SystemLifecycleCoordinator;
use think\console\Command;
use think\console\input\Argument;
use think\console\Input;
use think\console\Output;

final class SystemMigrate extends Command
{
    protected function configure(): void
    {
        $this->setName('lc:system:migrate')->setDescription('Run one resumable system migration phase')
            ->addArgument('directory', Argument::OPTIONAL, 'Migration directory', dirname(__DIR__, 2) . '/database/migrations')
            ->addOption('contract', 'c', null, 'Allow destructive contract phase')
            ->addOption('retry-failed', null, null, 'Explicitly retry the first failed migration');
    }

    protected function execute(Input $input, Output $output): int
    {
        $rows = app(SystemLifecycleCoordinator::class)->run((string) $input->getArgument('directory'), (bool) $input->getOption('contract'), (bool) $input->getOption('retry-failed'));
        foreach ($rows as $row) $output->writeln(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return 0;
    }
}
