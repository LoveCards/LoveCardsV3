<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Domain;

use app\modules\Platform\Eventing\Public\ExecutionMode;

final class ClaimedDelivery
{
    private int $id;
    private int $eventId;
    private string $handlerKey;
    private string $queueName;
    private string $executionMode;
    private int $attemptCount;
    private string $claimToken;
    private \DateTimeImmutable $leaseExpiresAt;
    private ?int $policyRevision;

    public function __construct(
        int $id,
        int $eventId,
        string $handlerKey,
        string $queueName,
        string $executionMode,
        int $attemptCount,
        string $claimToken,
        \DateTimeImmutable $leaseExpiresAt,
        ?int $policyRevision = null
    ) {
        if ($id < 1 || $eventId < 1 || $attemptCount < 1) {
            throw new \InvalidArgumentException('ClaimedDelivery 数值必须为正整数');
        }
        ExecutionMode::assert($executionMode);
        $this->id = $id;
        $this->eventId = $eventId;
        $this->handlerKey = $handlerKey;
        $this->queueName = $queueName;
        $this->executionMode = $executionMode;
        $this->attemptCount = $attemptCount;
        $this->claimToken = $claimToken;
        $this->leaseExpiresAt = $leaseExpiresAt;
        $this->policyRevision = $policyRevision;
    }

    public function id(): int { return $this->id; }
    public function eventId(): int { return $this->eventId; }
    public function handlerKey(): string { return $this->handlerKey; }
    public function queueName(): string { return $this->queueName; }
    public function executionMode(): string { return $this->executionMode; }
    public function attemptCount(): int { return $this->attemptCount; }
    public function claimToken(): string { return $this->claimToken; }
    public function leaseExpiresAt(): \DateTimeImmutable { return $this->leaseExpiresAt; }

    /** 事件登记时冻结的策略版本；NULL=策略化之前或未配置（not-configured）。 */
    public function policyRevision(): ?int { return $this->policyRevision; }
}
