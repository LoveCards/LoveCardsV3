<?php
declare(strict_types=1);

namespace think\facade {
    final class Env
    {
        public static array $values = [];
        public static function get(string $key, mixed $default = null): mixed
        {
            return self::$values[$key] ?? $default;
        }
    }
}

namespace app\modules\Platform\Config\Public {
    final class Config
    {
        public static array $values = [];
        public static function getGroup(string $group): array
        {
            return self::$values[$group] ?? [];
        }
    }
}

namespace {
    require dirname(__DIR__, 2) . '/app/modules/Platform/Config/Public/RedisConnectionSettings.php';

    use app\modules\Platform\Config\Public\Config;
    use app\modules\Platform\Config\Public\RedisConnectionSettings;
    use think\facade\Env;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    };

    Config::$values['redis'] = ['enabled' => false, 'host' => '', 'port' => 6379, 'database' => 0, 'password' => '', 'tls' => false, 'tls_server_name' => ''];
    $disabled = RedisConnectionSettings::current();
    $assert($disabled['enabled'] === false && $disabled['host'] === '' && $disabled['source'] === 'database', 'new install must leave Redis unconfigured');
    $assert(RedisConnectionSettings::test()['reason_code'] === 'redis-disabled', 'disabled Redis must not attempt connection');

    Config::$values['redis']['enabled'] = true;
    Config::$values['redis']['host'] = 'localhost';
    Config::$values['redis']['password'] = 'sample-secret';
    $database = RedisConnectionSettings::current();
    $assert($database['enabled'] === true && $database['password'] === 'sample-secret', 'database settings must reach PHP consumers');
    $assert($database['revision'] !== $disabled['revision'], 'revision must change with connection settings');
    $assert(!str_contains($database['revision'], 'sample-secret'), 'revision must not expose password');

    Config::$values['redis']['tls'] = true;
    Config::$values['redis']['tls_server_name'] = 'redis.example.test';
    $tls = RedisConnectionSettings::current();
    $assert($tls['tls'] === true && $tls['tls_server_name'] === 'redis.example.test', 'TLS settings must reach PHP consumers');
    $assert($tls['revision'] !== $database['revision'], 'TLS change must invalidate Gateway configuration version');
    Config::$values['redis']['host'] = '127.0.0.1';
    Config::$values['redis']['tls_server_name'] = '';
    $assert(RedisConnectionSettings::test()['reason_code'] === 'redis-tls-name-invalid', 'TLS with IP address requires a certificate name');

    Env::$values['LC_REDIS_HOST'] = 'redis.internal';
    Env::$values['LC_REDIS_PORT'] = 6380;
    Env::$values['LC_REDIS_TLS'] = 'true';
    Env::$values['LC_REDIS_TLS_SERVER_NAME'] = 'redis.internal';
    $configured = RedisConnectionSettings::current();
    $assert($configured['source'] === 'database' && $configured['host'] === '127.0.0.1' && $configured['port'] === 6379, 'legacy environment values must not override database group');
    $assert($configured['tls'] === true && $configured['tls_server_name'] === '', 'legacy environment TLS values must not override database group');

    echo "Redis connection settings baseline passed.\n";
}
