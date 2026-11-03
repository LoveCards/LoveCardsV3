<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

interface EventHandler
{
    public function handle(EventEnvelope $event, DeliveryContext $context): void;
}
