<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Public\RuntimeCommunication;
use app\modules\Platform\Eventing\Contract\SignalModePort;

/** 仅在组合根绑定；Eventing 不引用 Runtime 的具体门面。 */
final class EventingSignalModeAdapter implements SignalModePort
{
    public function selectedModeForEnqueue(): string
    {
        return app(RuntimeCommunication::class)->selectedModeForEnqueue();
    }

    public function signalDeliveryState(): string
    {
        return app(RuntimeCommunication::class)->signalDeliveryState();
    }
}
