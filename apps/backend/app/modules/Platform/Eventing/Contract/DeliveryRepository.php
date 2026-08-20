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

    public function complete(int $deliveryId, string $claimToken, \DateTimeImmutable $completedAt): bool;

    public function fail(
        int $deliveryId,
        string $claimToken,
        DeliveryFailure $failure,
        \DateTimeImmutable $failedAt
    ): bool;
}
