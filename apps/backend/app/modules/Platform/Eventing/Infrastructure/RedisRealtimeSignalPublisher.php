<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Config\Public\RedisConnectionSettings;
use app\modules\Platform\Eventing\Public\RealtimeSignal;
use app\modules\Platform\Eventing\Public\RealtimeSignalPublisher;
use app\modules\Platform\Eventing\Public\SignalTransportUnavailable;

/**
 * 经 Redis pub/sub 广播 realtime 信号（channel lovecards:realtime，契约 §4.5）。
 * 信号投递由 MySQL Delivery 重试；Redis/Gateway 不可用时必须报告失败。
 */
final class RedisRealtimeSignalPublisher implements RealtimeSignalPublisher
{
    public const CHANNEL = 'lovecards:realtime';

    public function publish(RealtimeSignal $signal): void
    {
        if (!extension_loaded('redis')) {
            throw new SignalTransportUnavailable('Realtime Redis 扩展不可用');
        }
        try {
            $settings = RedisConnectionSettings::current();
        } catch (\Throwable $exception) {
            throw new SignalTransportUnavailable('Realtime Redis 配置不可用', 0, $exception);
        }
        if (!$settings['enabled']) {
            throw new SignalTransportUnavailable('Realtime Redis 未启用');
        }
        $redis = new \Redis();
        try {
            if (!$redis->connect($settings['host'], $settings['port'], 0.25)) {
                throw new \RuntimeException('Realtime Redis 连接失败');
            }
            if ($settings['password'] !== '' && !$redis->auth($settings['password'])) {
                throw new \RuntimeException('Realtime Redis 认证失败');
            }
            if (!$redis->select($settings['database'])) {
                throw new \RuntimeException('Realtime Redis database 选择失败');
            }
            $subscribers = $redis->publish(self::CHANNEL, json_encode([
                'topic' => $signal->topic(),
                'uid' => $signal->recipientKey(),
                'cursor' => $signal->cursorHint(),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            if ($subscribers < 1) {
                throw new \RuntimeException('Realtime Gateway 未订阅 Redis 信号');
            }
        } catch (\Throwable $exception) {
            throw new SignalTransportUnavailable('Realtime 信号发布失败', 0, $exception);
        } finally {
            try {
                $redis->close();
            } catch (\Throwable $ignored) {
            }
        }
    }
}
