<?php
declare(strict_types=1);

namespace app\modules\Platform\Config\Infrastructure;

use app\modules\Platform\Config\Public\RedisConnectionSettings;
use app\modules\Platform\Config\Public\DeploymentNamespace;
use think\cache\driver\Redis;

/** ThinkPHP Redis 缓存只在显式选用时连接后台保存的系统 Redis。 */
final class SystemRedisCache extends Redis
{
    private string $connectionRevision = '';
    private ?array $connectionSettings = null;
    private int $settingsReadAt = 0;

    public function __construct(array $options = [])
    {
        $prefix = DeploymentNamespace::current()->cachePrefix();
        $options['prefix'] = $prefix;
        $options['tag_prefix'] = $prefix . 'tag:';
        parent::__construct($options);
    }

    public function handler()
    {
        $now = time();
        if ($this->connectionSettings === null || $now - $this->settingsReadAt >= 5) {
            $this->connectionSettings = RedisConnectionSettings::current();
            $this->settingsReadAt = $now;
        }
        $settings = $this->connectionSettings;
        if (!$settings['enabled'] || $settings['host'] === '' || $settings['port'] < 1 || $settings['port'] > 65535
            || $settings['database'] < 0 || $settings['database'] > 15) {
            throw new \RuntimeException('系统 Redis 未启用或连接配置无效');
        }
        if ($this->handler !== null && $this->connectionRevision === $settings['revision']) {
            return $this->handler;
        }
        if ($this->handler !== null) {
            try { $this->handler->close(); } catch (\Throwable $ignored) {}
            $this->handler = null;
        }

        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis 缓存需要 PhpRedis 扩展');
        }
        $redis = new \Redis();
        try {
            if (!RedisConnectionSettings::connectTransport($redis, $settings)) {
                throw new \RuntimeException('系统 Redis 连接失败');
            }
            if ($settings['password'] !== '' && !$redis->auth($settings['password'])) {
                throw new \RuntimeException('系统 Redis 认证失败');
            }
            if (!$redis->select($settings['database'])) {
                throw new \RuntimeException('系统 Redis 数据库不可用');
            }
        } catch (\Throwable $exception) {
            try { $redis->close(); } catch (\Throwable $ignored) {}
            throw $exception;
        }
        $this->handler = $redis;
        $this->connectionRevision = $settings['revision'];
        return $redis;
    }

    /** 系统 Redis 可供多个用途共享，清理缓存只删除本缓存前缀。 */
    public function clear(): bool
    {
        $redis = $this->handler();
        $prefix = (string) $this->options['prefix'];
        $cursor = null;
        do {
            $keys = $redis->scan($cursor, $prefix . '*', 200);
            if (is_array($keys) && $keys !== []) $redis->del($keys);
        } while ($cursor !== 0);
        return true;
    }
}
