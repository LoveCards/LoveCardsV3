<?php

namespace app\shared\Infrastructure\Cache;

use think\facade\Cache;

/** Framework cache adapter without domain semantics. */
final class CacheManager
{
    public const TTL_SHORT = 60;
    public const TTL_MEDIUM = 300;
    public const TTL_LONG = 3600;
    public const TTL_DAY = 86400;
    public const TTL_3_DAYS = 259200;
    public static function get(string $domain, string $key, ?callable $loader = null, int $ttl = 0): mixed { $value = Cache::get($key); if ($value !== null) return $value; if ($loader === null) return null; $value = $loader(); self::set($domain, $key, $value, $ttl); return $value; }
    public static function set(string $domain, string $key, mixed $value, int $ttl = 0): void { Cache::tag($domain)->set($key, $value, $ttl ?: null); }
    public static function delete(string $key): void { Cache::delete($key); }
    public static function has(string $key): bool { return Cache::has($key); }
    public static function clearDomain(string $domain): void { Cache::tag($domain)->clear(); }
    public static function key(string ...$parts): string { return implode(':', $parts); }
}
