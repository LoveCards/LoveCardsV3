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
    private ?string $signalDecision = null;
    private ?array $signalOrigin = null;
    private ?float $signalDeadline = null;

    public function __construct(
        int $deliveryId,
        int $eventId,
        string $handlerKey,
        int $attempt,
        ?int $policyRevision = null,
        string $eventType = '',
        private ?string $claimToken = null
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

    public function claimToken(): ?string { return $this->claimToken; }

    /** 单次授权最多允许五秒的当前批次；续批和重试重新核验原代次。 */
    public function grantSignal(string $key, int $generation, float $seconds): void
    {
        if ($seconds <= 0 || $generation < 1) throw new \LogicException('信号授权窗口已过期');
        $this->signalOrigin = ['key' => $key, 'generation' => $generation];
        $this->signalDeadline = hrtime(true) / 1e9 + min(5.0, $seconds);
    }

    public function signalOrigin(): array
    {
        $this->assertSignalWindow();
        return $this->signalOrigin;
    }

    public function assertSignalWindow(): void
    {
        if ($this->signalOrigin === null || $this->signalDeadline === null || hrtime(true) / 1e9 >= $this->signalDeadline) {
            throw new SignalTransportUnavailable('信号发送资格窗口已过期');
        }
    }

    /** 主动信号 Handler 在成功返回前记录最终结果，由投递完成操作原子落库。 */
    public function markSignalDecision(string $decision): void
    {
        if (!in_array($decision, ['applied', 'skipped'], true) || $this->signalDecision !== null) {
            throw new \InvalidArgumentException('Realtime signal 决策不合法或已记录');
        }
        $this->signalDecision = $decision;
    }

    public function signalDecision(): ?string { return $this->signalDecision; }

    public function idempotencyKey(): string
    {
        return "event:{$this->eventId}:handler:{$this->handlerKey}";
    }
}
