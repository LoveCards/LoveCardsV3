<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Contract;

/** Eventing 只需要通信模式的两个判定，不依赖 Runtime 控制面的实现。 */
interface SignalModePort
{
    public function selectedModeForEnqueue(): string;

    public function signalDeliveryState(): string;
}
