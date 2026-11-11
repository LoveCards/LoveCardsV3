<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Config\Public\RedisConnectionSettings;
use app\modules\Platform\Eventing\Public\RealtimeSignal;
use app\modules\Platform\Eventing\Public\RealtimeSignalPublisher;
use app\modules\Platform\Eventing\Public\SignalTransportUnavailable;

/**
 * 经部署身份隔离的 Redis pub/sub channel 广播 realtime 信号。
 * 信号投递由 MySQL Delivery 重试；Redis/Gateway 不可用时必须报告失败。
 */
final class RedisRealtimeSignalPublisher implements RealtimeSignalPublisher
{
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
            if (!RedisConnectionSettings::connectTransport($redis, $settings)) {
                throw new \RuntimeException('Realtime Redis 连接失败');
            }
            if ($settings['password'] !== '' && !$redis->auth($settings['password'])) {
                throw new \RuntimeException('Realtime Redis 认证失败');
            }
            if (!$redis->select($settings['database'])) {
                throw new \RuntimeException('Realtime Redis database 选择失败');
            }
            $recipients = $signal->recipientKeys();
            $payload = [
                'topic' => $signal->topic(),
                'cursor' => $signal->cursorHint(),
            ];
            $payload[count($recipients) === 1 ? 'uid' : 'uids'] = count($recipients) === 1 ? $recipients[0] : $recipients;
            $subscribers = $redis->publish($settings['channel'], json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
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
