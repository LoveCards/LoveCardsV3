<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

final class RecordedEvent
{
    private int $eventId;
    private array $deliveryIds;

    public function __construct(int $eventId, array $deliveryIds)
    {
        if ($eventId < 1) {
            throw new \InvalidArgumentException('event_id 必须为正整数');
        }
        foreach ($deliveryIds as $deliveryId) {
            if (!is_int($deliveryId) || $deliveryId < 1) {
                throw new \InvalidArgumentException('delivery_ids 必须为正整数数组');
            }
        }
        $this->eventId = $eventId;
        $this->deliveryIds = array_values($deliveryIds);
    }

    public function eventId(): int { return $this->eventId; }
    public function deliveryIds(): array { return $this->deliveryIds; }
}
