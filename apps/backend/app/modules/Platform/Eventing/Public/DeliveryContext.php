<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

final class DeliveryContext
{
    private int $deliveryId;
    private int $eventId;
    private string $handlerKey;
    private int $attempt;
    private ?int $policyRevision;
    private string $eventType;

    public function __construct(
        int $deliveryId,
        int $eventId,
        string $handlerKey,
        int $attempt,
        ?int $policyRevision = null,
        string $eventType = ''
    ) {
        if ($deliveryId < 1 || $eventId < 1 || $attempt < 1) {
            throw new \InvalidArgumentException('DeliveryContext 数值必须为正整数');
        }
        $this->deliveryId = $deliveryId;
        $this->eventId = $eventId;
        $this->handlerKey = $handlerKey;
        $this->attempt = $attempt;
        $this->policyRevision = $policyRevision;
        $this->eventType = $eventType;
    }

    public function deliveryId(): int { return $this->deliveryId; }
    public function eventId(): int { return $this->eventId; }
    public function handlerKey(): string { return $this->handlerKey; }
    public function attempt(): int { return $this->attempt; }

    /**
     * 投影前策略决策范围：[event_type, 冻结 policy_revision, delivery_id]。
     * 非事件投递（无 eventType）返回 null，签发器按旧路径执行。
     *
     * @return array{0: string, 1: ?int, 2: int}|null
     */
    public function policyScope(): ?array
    {
        if ($this->eventType === '') {
            return null;
        }
        return [$this->eventType, $this->policyRevision, $this->deliveryId];
    }

    public function policyRevision(): ?int { return $this->policyRevision; }

    public function eventType(): string { return $this->eventType; }

    public function idempotencyKey(): string
    {
        return "event:{$this->eventId}:handler:{$this->handlerKey}";
    }
}
