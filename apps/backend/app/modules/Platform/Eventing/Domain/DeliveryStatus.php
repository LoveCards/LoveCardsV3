<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Domain;

final class DeliveryStatus
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const DEAD = 'dead';
}
