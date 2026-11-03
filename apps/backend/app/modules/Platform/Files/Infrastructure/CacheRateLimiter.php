<?php

namespace app\modules\Platform\Files\Infrastructure;

use app\modules\Platform\Files\Contract\RateLimiter;
use app\modules\Platform\Storage\Public\Gateway;
use app\shared\Infrastructure\Cache\CacheManager;

/**
 * 基于 CacheManager 的限流实现
 *
 * 封装 CacheManager 和 ChannelManager 的静态调用，
 * 使 Application 层通过 RateLimiter Port 访问。
 */
class CacheRateLimiter implements RateLimiter
{
    public function checkUploadRate(string $uid): bool
    {
        $settings = class_exists(Gateway::class) ? Gateway::rateLimitSettings() : \app\modules\Platform\Storage\Infrastructure\ChannelManager::getRateLimitSettings();
        $max = $settings['max'];
        $window = $settings['window'];

        $key = 'rate_limit_upload_' . $uid;
        $timestamps = CacheManager::get('storage', $key) ?? [];

        $now = time();
        $timestamps = array_filter($timestamps, fn($t) => $t > $now - $window);

        if (count($timestamps) >= $max) {
            return false;
        }

        $timestamps[] = $now;
        CacheManager::set('storage', $key, array_values($timestamps), $window);

        return true;
    }
}
