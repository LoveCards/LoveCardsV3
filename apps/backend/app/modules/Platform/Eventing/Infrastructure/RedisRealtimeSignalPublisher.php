<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Eventing\Public\RealtimeSignal;
use app\modules\Platform\Eventing\Public\RealtimeSignalPublisher;

/**
 * 经 Redis pub/sub 广播 realtime 信号（channel lovecards:realtime，契约 §4.5）。
 * 与 OptionalRedisWakeupTransport 同款 best-effort：通道不可用不影响事实落库，
 * Gateway 会退回 source-poll 回源，runtime 快照据此报 realtime-transport-degraded。
 */
final class RedisRealtimeSignalPublisher implements RealtimeSignalPublisher
{
    public const CHANNEL = 'lovecards:realtime';

    public function publish(RealtimeSignal $signal): void
    {
        if (!extension_loaded('redis')) {
            return;
        }
        $redis = new \Redis();
        $host = (string) env('LC_REDIS_HOST', '127.0.0.1');
        $port = (int) env('LC_REDIS_PORT', 6379);
        try {
            if (!$redis->connect($host, $port, 0.25)) {
                return;
            }
            $redis->publish(self::CHANNEL, json_encode([
                'topic' => $signal->topic(),
                'uid' => $signal->recipientKey(),
                'cursor' => $signal->cursorHint(),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $ignored) {
            // 信号是加速通道，丢失后由 Gateway source-poll 与客户端轮询补偿。
        } finally {
            try {
                $redis->close();
            } catch (\Throwable $ignored) {
            }
        }
    }
}
