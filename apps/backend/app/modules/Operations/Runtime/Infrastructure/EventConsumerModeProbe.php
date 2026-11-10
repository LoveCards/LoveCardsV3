<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Contract\RuntimeProbe;
use app\modules\Platform\Eventing\Public\EventConsumption;

/** 前端实时模式要求事件消费者已选择常驻且没有处于切换中。 */
final class EventConsumerModeProbe implements RuntimeProbe
{
    public function key(): string
    {
        return 'event-consumer';
    }

    public function probe(array $settings): array
    {
        $state = (new EventConsumption())->current();
        if ($state === null) {
            return ['ready' => false, 'provider' => 'mysql-eventing_consumer_state', 'reason_code' => 'event-consumer-unavailable'];
        }
        $ready = ($state['mode'] ?? null) === 'resident' && ($state['state'] ?? null) === 'active';
        return [
            'ready' => $ready,
            'provider' => 'mysql-eventing_consumer_state',
            'reason_code' => $ready ? null : (($state['state'] ?? null) === 'draining' ? 'event-consumer-draining' : 'event-consumer-not-resident'),
            'note' => ['mode' => $state['mode'] ?? null, 'state' => $state['state'] ?? null],
        ];
    }
}
