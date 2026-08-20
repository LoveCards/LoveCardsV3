<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Domain;

final class DeliveryOutcome
{
    public const COMPLETED = 'completed';
    public const RETRY_SCHEDULED = 'retry_scheduled';
    public const DEAD = 'dead';
    public const FENCED = 'fenced';
}
