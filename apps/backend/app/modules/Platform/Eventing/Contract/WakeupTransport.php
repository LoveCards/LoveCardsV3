<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Contract;

interface WakeupTransport
{
    /**
     * 仅发送可丢失的 Delivery 唤醒信号；权威任务始终保存在 MySQL。
     */
    public function publish(array $deliveryIds): void;
}
