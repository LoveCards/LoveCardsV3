<?php

namespace app\common\infra;

/** @deprecated use app\shared\Infrastructure\Cache\CacheManager. */
final class CacheManager
{
    public const TTL_SHORT = \app\shared\Infrastructure\Cache\CacheManager::TTL_SHORT;
    public const TTL_MEDIUM = \app\shared\Infrastructure\Cache\CacheManager::TTL_MEDIUM;
    public const TTL_LONG = \app\shared\Infrastructure\Cache\CacheManager::TTL_LONG;
    public const TTL_DAY = \app\shared\Infrastructure\Cache\CacheManager::TTL_DAY;
    public const TTL_3_DAYS = \app\shared\Infrastructure\Cache\CacheManager::TTL_3_DAYS;
    public static function get(string $domain, string $key, ?callable $loader = null, int $ttl = 0): mixed { return \app\shared\Infrastructure\Cache\CacheManager::get($domain, $key, $loader, $ttl); }
    public static function set(string $domain, string $key, mixed $value, int $ttl = 0): void { \app\shared\Infrastructure\Cache\CacheManager::set($domain, $key, $value, $ttl); }
    public static function delete(string $key): void { \app\shared\Infrastructure\Cache\CacheManager::delete($key); }
    public static function has(string $key): bool { return \app\shared\Infrastructure\Cache\CacheManager::has($key); }
    public static function clearDomain(string $domain): void { \app\shared\Infrastructure\Cache\CacheManager::clearDomain($domain); }
    public static function key(string ...$parts): string { return \app\shared\Infrastructure\Cache\CacheManager::key(...$parts); }
}
