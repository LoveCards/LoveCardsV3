<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

use app\modules\Platform\Eventing\Infrastructure\ThinkDbDeliveryRepository;

/** 供运行时切换用例结算待发送信号的稳定入口。 */
final class SignalDeliveryControl
{
    public static function settleForPolling(): int
    {
        return (new ThinkDbDeliveryRepository())->settleRealtimeForPolling();
    }
}
