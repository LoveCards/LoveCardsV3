<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Contract;

use app\modules\Platform\Eventing\Public\EventEnvelope;

interface OutboxEventRepository
{
    public function append(EventEnvelope $event): int;

    public function findById(int $eventId): ?EventEnvelope;
}
