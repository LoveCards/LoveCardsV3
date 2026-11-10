<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Contract;

use app\modules\Platform\Eventing\Domain\DeliveryClaim;
use app\modules\Platform\Eventing\Domain\DeliveryDefinition;
use app\modules\Platform\Eventing\Domain\DeliveryFailure;

interface DeliveryRepository
{
    /**
     * @param DeliveryDefinition[] $definitions
     * @return int[]
     */
    public function enqueue(int $eventId, array $definitions, \DateTimeImmutable $availableAt): array;

    /**
     * @return \app\modules\Platform\Eventing\Domain\ClaimedDelivery[]
     */
    public function claim(DeliveryClaim $claim): array;

    /** 策略决策与完成状态必须由同一 claim token 条件更新。 */
    public function complete(int $deliveryId, string $claimToken, \DateTimeImmutable $completedAt, ?string $policyDecision = null): bool;

    public function fail(
        int $deliveryId,
        string $claimToken,
        DeliveryFailure $failure,
        \DateTimeImmutable $failedAt
    ): bool;

    /** 设施故障延后信号，撤销本次 claim 产生的 attempt 增量。 */
    public function defer(int $deliveryId, string $claimToken, DeliveryFailure $failure, \DateTimeImmutable $deferredAt): bool;

    /**
     * 记录投影前策略决策（applied/skipped/not-configured，契约 §5.2 D6）。
     * 只写列，不改变 DeliveryStatus 枚举。
     */
    public function notePolicyDecision(int $deliveryId, string $decision): void;
}
