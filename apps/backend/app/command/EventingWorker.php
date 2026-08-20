<?php
declare(strict_types=1);
namespace app\command;
use think\console\Command;
use think\console\input\Option;
use think\console\Input;
use think\console\Output;
use app\modules\Platform\Eventing\Application\WorkerLoop;
use app\modules\Platform\Eventing\Domain\WorkerOptions;
final class EventingWorker extends Command {
 protected function configure(){ $this->setName('lc:eventing:work')->setDescription('运行 LoveCards Outbox Event Worker')->addOption('max-jobs',null,Option::VALUE_OPTIONAL,'最大任务数','0'); }
 protected function execute(Input $input,Output $output){ $releaseId=getenv('LC_ACTIVE_RELEASE_ID'); $releaseId=is_string($releaseId)&&$releaseId!==''?$releaseId:'legacy'; $options=new WorkerOptions('eventing-'.getmypid(),['critical','default','bulk'],30,1000,10000,(int)$input->getOption('max-jobs'),0,$releaseId,'current'); $report=app(WorkerLoop::class)->run($options,static fn()=>false); $output->writeln('claimed='.$report->claimed()); return 0; }
}
