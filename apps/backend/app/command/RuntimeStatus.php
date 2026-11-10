<?php
declare(strict_types=1);

namespace app\command;

use app\modules\Operations\Runtime\Public\RuntimeCommunication;
use think\console\Command;
use think\console\Input;
use think\console\Output;

final class RuntimeStatus extends Command
{
    protected function configure(): void { $this->setName('lc:runtime:status')->setDescription('显示事件消费与前端通信的所选模式和健康状态'); }
    protected function execute(Input $input, Output $output): int
    {
        $status = app(RuntimeCommunication::class)->statusSnapshot();
        $communication = $status['communication'];
        $event = $status['event_processing'];
        // CLI 只输出诊断白名单；配置内容、探测原始 note 和凭据不得进入终端日志。
        $safe = [
            'communication' => [
                'desired_mode' => $communication['desired_mode'],
                'effective_mode' => $communication['effective_mode'],
                'status' => $communication['status'],
                'reason_code' => $communication['reason_code'],
                'checked_at' => $communication['checked_at'],
                'capabilities' => array_map(static fn (array $item): array => [
                    'key' => $item['key'],
                    'ready' => $item['ready'],
                    'provider' => $item['provider'],
                    'reason_code' => $item['reason_code'],
                ], $communication['capabilities']),
            ],
            'event_processing' => [
                'mode' => $event['mode'],
                'state' => $event['state'],
                'target_mode' => $event['target_mode'],
                'scheduled_trigger' => $event['scheduled_trigger'],
                'status' => $event['status'],
                'reason_code' => $event['reason_code'],
            ],
        ];
        $output->writeln(json_encode(
            $safe,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
        return 0;
    }
}
