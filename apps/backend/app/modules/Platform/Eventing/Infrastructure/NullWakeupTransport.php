<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Eventing\Contract\WakeupTransport;

final class NullWakeupTransport implements WakeupTransport
{
    public function publish(array $deliveryIds): void
    {
        // Minimal Profile 依靠 MySQL 扫描，不需要额外唤醒通道。
    }
}
