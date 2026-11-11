<?php
declare(strict_types=1);

namespace app\modules\Platform\Config\Public;

use think\facade\Env;

/** PHP Redis 使用者共用的系统配置视图；不会返回给管理端 Resource。 */
final class RedisConnectionSettings
{
    private const IO_TIMEOUT_SECONDS = 0.25;
    /** @return array{enabled:bool,host:string,port:int,database:int,password:string,tls:bool,tls_server_name:string,source:string,revision:string,channel:string} */
    public static function current(): array
    {
        $values = Config::getGroup('redis');
        $settings = [
            'enabled' => ($values['enabled'] ?? false) === true,
            'host' => trim((string) ($values['host'] ?? '')),
            'port' => (int) ($values['port'] ?? 0),
            'database' => (int) ($values['database'] ?? 0),
            'password' => (string) ($values['password'] ?? ''),
            'tls' => ($values['tls'] ?? false) === true,
            'tls_server_name' => trim((string) ($values['tls_server_name'] ?? '')),
            'source' => 'database',
            'channel' => ($values['enabled'] ?? false) === true ? DeploymentNamespace::current()->realtimeChannel() : '',
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

    /** 连接传输层在所有 PHP Redis 使用者之间共用，TLS 失败绝不退回明文。 */
    public static function connectTransport(\Redis $redis, array $settings): bool
    {
        if (($settings['tls'] ?? false) !== true) {
            return $redis->connect($settings['host'], $settings['port'], self::IO_TIMEOUT_SECONDS, null, 0, self::IO_TIMEOUT_SECONDS);
        }
        if (!extension_loaded('openssl') || (extension_loaded('redis') && version_compare((string) phpversion('redis'), '5.3.0', '<'))) {
            throw new \RuntimeException('Redis TLS 需要 OpenSSL 和 PhpRedis 5.3 或更高版本');
        }
        $identity = self::tlsIdentity($settings);
        if ($identity === null) {
            throw new \InvalidArgumentException('Redis TLS 证书名称无效');
        }
        return $redis->connect(
            'tls://' . $settings['host'], $settings['port'], self::IO_TIMEOUT_SECONDS, null, 0, self::IO_TIMEOUT_SECONDS,
            ['stream' => ['verify_peer' => true, 'verify_peer_name' => true,
                'allow_self_signed' => false, 'peer_name' => $identity, 'SNI_enabled' => true]]
        );
    }

    public static function tlsIdentity(array $settings): ?string
    {
        $name = trim((string) ($settings['tls_server_name'] ?? ''));
        if ($name === '') $name = trim((string) ($settings['host'] ?? ''));
        if (filter_var($name, FILTER_VALIDATE_IP) !== false) return null;
        if (strlen($name) > 253 || preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(?:\.(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?))*$/D', $name) !== 1) return null;
        return $name;
    }

    /** @return array{ready:bool,reason_code:?string,source:string,revision:string} */
    public static function test(): array
    {
        try {
            $settings = self::current();
        } catch (\InvalidArgumentException $exception) {
            return ['ready' => false, 'reason_code' => 'deployment-identity-required', 'source' => 'database', 'revision' => ''];
        }
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
        if ($settings['tls'] && self::tlsIdentity($settings) === null) {
            $result['reason_code'] = 'redis-tls-name-invalid';
            return $result;
        }
        if (!extension_loaded('redis')) {
            $result['reason_code'] = 'redis-extension-unavailable';
            return $result;
        }
        if ($settings['tls'] && (!extension_loaded('openssl') || version_compare((string) phpversion('redis'), '5.3.0', '<'))) {
            $result['reason_code'] = 'redis-tls-extension-unsupported';
            return $result;
        }

        $redis = new \Redis();
        try {
            if (!self::connectTransport($redis, $settings)) {
                $result['reason_code'] = $settings['tls'] ? 'redis-tls-failed' : 'redis-unreachable';
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
            $result['reason_code'] = $settings['tls'] ? 'redis-tls-failed' : 'redis-unreachable';
            return $result;
        } finally {
            try { $redis->close(); } catch (\Throwable $ignored) {}
        }
    }
}
