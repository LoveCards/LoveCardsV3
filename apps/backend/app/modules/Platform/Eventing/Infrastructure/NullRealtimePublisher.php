<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Eventing\Contract\RealtimePublisher;
use app\modules\Platform\Eventing\Domain\RealtimeSignal;

final class NullRealtimePublisher implements RealtimePublisher
{
    public function publish(RealtimeSignal $signal): void
    {
        // 无 Realtime Facility 时，客户端通过刷新或游标轮询读取权威数据。
    }
}
