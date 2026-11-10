<?php
declare(strict_types=1);

namespace app\modules\Platform\Config\Public;

use think\facade\Env;

/** PHP Redis 使用者共用的系统配置视图；不会返回给管理端 Resource。 */
final class RedisConnectionSettings
{
    /** @return array{enabled:bool,host:string,port:int,database:int,password:string,source:string,revision:string} */
    public static function current(): array
    {
        // 既有部署的 LC_REDIS_HOST 是显式环境托管；迁移期间优先保留。
        $envHost = trim((string) Env::get('LC_REDIS_HOST', ''));
        if ($envHost !== '') {
            $settings = [
                'enabled' => true,
                'host' => $envHost,
                'port' => (int) Env::get('LC_REDIS_PORT', 6379),
                'database' => (int) Env::get('LC_REDIS_DATABASE', 0),
                'password' => (string) Env::get('LC_REDIS_PASSWORD', ''),
                'source' => 'env',
            ];
            return self::versioned($settings);
        }

        $values = Config::getGroup('redis');
        $settings = [
            'enabled' => ($values['enabled'] ?? false) === true,
            'host' => trim((string) ($values['host'] ?? '')),
            'port' => (int) ($values['port'] ?? 0),
            'database' => (int) ($values['database'] ?? 0),
            'password' => (string) ($values['password'] ?? ''),
            'source' => 'database',
        ];
        return self::versioned($settings);
    }

    private static function versioned(array $settings): array
    {
        // 版本为完整连接配置的摘要；内部 API 仅交给已认证的 Gateway。
        $settings['revision'] = hash_hmac(
            'sha256',
            json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            (string) Env::get('LC_RUNTIME_INTERNAL_TOKEN', '')
        );
        return $settings;
    }

    /** @return array{ready:bool,reason_code:?string,source:string,revision:string} */
    public static function test(): array
    {
        $settings = self::current();
        $result = ['ready' => false, 'reason_code' => null, 'source' => $settings['source'], 'revision' => $settings['revision']];
        if (!$settings['enabled']) {
            $result['reason_code'] = 'redis-disabled';
            return $result;
        }
        if ($settings['host'] === '' || $settings['port'] < 1 || $settings['port'] > 65535
            || $settings['database'] < 0 || $settings['database'] > 15) {
            $result['reason_code'] = 'redis-not-configured';
            return $result;
        }
        if (!extension_loaded('redis')) {
            $result['reason_code'] = 'redis-extension-unavailable';
            return $result;
        }

        $redis = new \Redis();
        try {
            if (!$redis->connect($settings['host'], $settings['port'], 0.25)) {
                $result['reason_code'] = 'redis-unreachable';
                return $result;
            }
            if ($settings['password'] !== '' && !$redis->auth($settings['password'])) {
                $result['reason_code'] = 'redis-auth-failed';
                return $result;
            }
            if (!$redis->select($settings['database'])) {
                $result['reason_code'] = 'redis-database-unavailable';
                return $result;
            }
            if ($redis->ping() === false) {
                $result['reason_code'] = 'redis-unreachable';
                return $result;
            }
            $result['ready'] = true;
            return $result;
        } catch (\Throwable $ignored) {
            $result['reason_code'] = 'redis-unreachable';
            return $result;
        } finally {
            try { $redis->close(); } catch (\Throwable $ignored) {}
        }
    }
}
