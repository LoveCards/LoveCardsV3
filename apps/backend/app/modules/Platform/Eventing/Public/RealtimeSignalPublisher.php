<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

/**
 * Realtime 信号发布端口（契约 §4.5）。
 * 发布失败必须抛出异常，由 MySQL Delivery 保留可重试进度。
 * 发布成功只表示 Gateway 订阅者收到信号，不表示浏览器已读取。
 */
interface RealtimeSignalPublisher
{
    public function publish(RealtimeSignal $signal): void;
}
