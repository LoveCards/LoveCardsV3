<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Application;

use app\modules\Platform\Eventing\Contract\WakeupTransport;
use app\modules\Platform\Eventing\Public\RecordedEvent;

final class DeliveryWakeup
{
    private WakeupTransport $transport;

    public function __construct(WakeupTransport $transport)
    {
        $this->transport = $transport;
    }

    public function publish(RecordedEvent $event): void
    {
        if ($event->deliveryIds() !== []) {
            $this->transport->publish($event->deliveryIds());
        }
    }
}
