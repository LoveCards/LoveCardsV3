<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Config\Public\RedisConnectionSettings;
use app\modules\Platform\Eventing\Contract\WakeupTransport;

final class OptionalRedisWakeupTransport implements WakeupTransport
{
    public function publish(array $deliveryIds): void
    {
        if ($deliveryIds === [] || !extension_loaded('redis')) return;
        try {
            $settings = RedisConnectionSettings::current();
        } catch (\Throwable $ignored) {
            return;
        }
        if (!$settings['enabled']) return;
        $redis = new \Redis();
        try {
            if (!RedisConnectionSettings::connectTransport($redis, $settings)) return;
            if ($settings['password'] !== '' && !$redis->auth($settings['password'])) return;
            if (!$redis->select($settings['database'])) return;
            $redis->publish('lovecards:eventing', json_encode(array_values($deliveryIds), JSON_THROW_ON_ERROR));
        } catch (\Throwable $ignored) {
            // 唤醒是尽力而为；MySQL 投递账本仍是事实源。
        } finally {
            try { $redis->close(); } catch (\Throwable $ignored) {}
        }
    }
}
