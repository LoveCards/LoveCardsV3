<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Contract\RuntimeProbe;
use app\modules\Platform\Config\Public\RedisConnectionSettings;

/** 实时信号发布端的 Redis 探测；与 Publisher 共用系统配置。 */
final class RedisRealtimeProbe implements RuntimeProbe
{
    public function key(): string
    {
        return 'realtime-redis';
    }

    public function probe(array $settings): array
    {
        try {
            $result = RedisConnectionSettings::test();
            return [
                'ready' => $result['ready'],
                'provider' => 'redis-' . $result['source'],
                'reason_code' => $result['reason_code'],
                'note' => ['source' => $result['source']],
            ];
        } catch (\Throwable $ignored) {
            return ['ready' => false, 'provider' => null, 'reason_code' => 'probe-unavailable'];
        }
    }
}
