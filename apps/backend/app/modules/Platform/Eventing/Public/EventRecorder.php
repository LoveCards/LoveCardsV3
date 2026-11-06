<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

use app\modules\Platform\Eventing\Contract\Clock;
use app\modules\Platform\Eventing\Contract\DeliveryRepository;
use app\modules\Platform\Eventing\Contract\OutboxEventRepository;
use app\modules\Platform\Eventing\Domain\DeliveryDefinition;

final class EventRecorder
{
    private OutboxEventRepository $events;
    private DeliveryRepository $deliveries;
    private HandlerRegistry $handlers;
    private Clock $clock;

    public function __construct(
        OutboxEventRepository $events,
        DeliveryRepository $deliveries,
        HandlerRegistry $handlers,
        Clock $clock
    ) {
        $this->events = $events;
        $this->deliveries = $deliveries;
        $this->handlers = $handlers;
        $this->clock = $clock;
    }

    /**
     * 调用者必须把本方法放在当前业务事务内，使业务写入、Outbox 与 Delivery 同步提交。
     * 唤醒 Redis 等外部 I/O 必须在事务提交后单独执行。
     */
    public function record(EventEnvelope $event): RecordedEvent
    {
        $eventId = $this->events->append($event);
        $definitions = array_map(
            static fn(HandlerRegistration $registration): DeliveryDefinition =>
                DeliveryDefinition::fromRegistration($registration),
            $this->handlers->handlersFor($event)
        );
        $deliveryIds = $definitions === []
            ? []
            : $this->deliveries->enqueue($eventId, $definitions, $this->clock->now());

        return new RecordedEvent($eventId, $deliveryIds);
    }

    /** 投影前策略决策落列（applied/skipped/not-configured，契约 §5.2 D6）。 */
    public function notePolicyDecision(int $deliveryId, string $decision): void
    {
        $this->deliveries->notePolicyDecision($deliveryId, $decision);
    }
}
