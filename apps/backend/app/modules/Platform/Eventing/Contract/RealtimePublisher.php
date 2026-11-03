<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Contract;

use app\modules\Platform\Eventing\Domain\RealtimeSignal;

interface RealtimePublisher
{
    /**
     * Realtime 信号不承载业务事实，客户端收到后仍通过游标 API 回源读取。
     */
    public function publish(RealtimeSignal $signal): void;
}
