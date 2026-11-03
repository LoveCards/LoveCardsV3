<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Eventing\Contract\WakeupTransport;

final class OptionalRedisWakeupTransport implements WakeupTransport
{
    public function publish(array $deliveryIds): void
    {
        if ($deliveryIds === [] || !extension_loaded('redis')) return;
        $redis = new \Redis();
        $host = (string) env('LC_REDIS_HOST', '127.0.0.1'); $port = (int) env('LC_REDIS_PORT', 6379);
        try { if (!$redis->connect($host, $port, 0.25)) return; $redis->publish('lovecards:eventing', json_encode(array_values($deliveryIds), JSON_THROW_ON_ERROR)); }
        catch (\Throwable $ignored) { /* MySQL remains authoritative; wakeup is best effort. */ }
        finally { try { $redis->close(); } catch (\Throwable $ignored) {} }
    }
}
