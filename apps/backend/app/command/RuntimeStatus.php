<?php
declare(strict_types=1);

namespace app\command;

use app\modules\Operations\Runtime\Application\ResolveFacilities;
use app\shared\Runtime\RuntimeFacilityCatalog;
use think\console\Command;
use think\console\Input;
use think\console\Output;

final class RuntimeStatus extends Command
{
    protected function configure(): void { $this->setName('lc:runtime:status')->setDescription('Show enabled and fallback runtime facilities'); }
    protected function execute(Input $input, Output $output): int
    {
        $statuses = app(ResolveFacilities::class)->execute(app(RuntimeFacilityCatalog::class)->configs());
        foreach ($statuses as $status) $output->writeln(json_encode($status->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return 0;
    }
}
