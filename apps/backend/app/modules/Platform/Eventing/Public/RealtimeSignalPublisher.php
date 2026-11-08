<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

/**
 * Realtime 信号发布端口（契约 §4.5）。
 * 实现必须 best-effort：外部通道不可用时静默降级，MySQL/游标 API 仍是事实源。
 */
interface RealtimeSignalPublisher
{
    public function publish(RealtimeSignal $signal): void;
}
