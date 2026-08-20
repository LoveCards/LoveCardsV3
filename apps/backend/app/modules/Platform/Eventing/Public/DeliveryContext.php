<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

final class DeliveryContext
{
    private int $deliveryId;
    private int $eventId;
    private string $handlerKey;
    private int $attempt;

    public function __construct(int $deliveryId, int $eventId, string $handlerKey, int $attempt)
    {
        if ($deliveryId < 1 || $eventId < 1 || $attempt < 1) {
            throw new \InvalidArgumentException('DeliveryContext 数值必须为正整数');
        }
        $this->deliveryId = $deliveryId;
        $this->eventId = $eventId;
        $this->handlerKey = $handlerKey;
        $this->attempt = $attempt;
    }

    public function deliveryId(): int { return $this->deliveryId; }
    public function eventId(): int { return $this->eventId; }
    public function handlerKey(): string { return $this->handlerKey; }
    public function attempt(): int { return $this->attempt; }

    public function idempotencyKey(): string
    {
        return "event:{$this->eventId}:handler:{$this->handlerKey}";
    }
}
