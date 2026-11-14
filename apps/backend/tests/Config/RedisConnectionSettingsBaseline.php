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
    if (!class_exists('Redis')) {
        class Redis { public function connect(...$arguments): bool { return true; } }
    }
    require dirname(__DIR__, 2) . '/vendor/autoload.php';
    require dirname(__DIR__, 2) . '/app/modules/Platform/Config/Public/RedisConnectionSettings.php';

    use app\modules\Platform\Config\Public\Config;
    use app\modules\Platform\Config\Public\RedisConnectionSettings;
    use think\facade\Env;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    };

    $transport = new class extends \Redis {
        public array $arguments = [];
        public function connect(...$arguments): bool { $this->arguments = $arguments; return true; }
    };
    RedisConnectionSettings::connectTransport($transport, ['host' => 'localhost', 'port' => 6379, 'tls' => false]);
    $assert(($transport->arguments[2] ?? 0) > 0 && ($transport->arguments[5] ?? 0) > 0,
        'plaintext Redis must bound both connection and command reads');
    if (extension_loaded('openssl') && (!extension_loaded('redis') || version_compare((string) phpversion('redis'), '5.3.0', '>='))) {
        RedisConnectionSettings::connectTransport($transport, ['host' => 'localhost', 'port' => 6379, 'tls' => true, 'tls_server_name' => 'localhost']);
        $assert(($transport->arguments[2] ?? 0) > 0 && ($transport->arguments[5] ?? 0) > 0,
            'TLS Redis must use the same bounded connection and command reads');
    } else {
        echo "SKIP TLS transport argument check: OpenSSL/PhpRedis prerequisites unavailable.\n";
    }

    Config::$values['redis'] = ['enabled' => false, 'host' => '', 'port' => 6379, 'database' => 0, 'password' => '', 'tls' => false, 'tls_server_name' => ''];
    $disabled = RedisConnectionSettings::current();
    $assert($disabled['enabled'] === false && $disabled['host'] === '' && $disabled['source'] === 'database', 'new install must leave Redis unconfigured');
    $assert(RedisConnectionSettings::test()['reason_code'] === 'redis-disabled', 'disabled Redis must not attempt connection');
    putenv('LC_DEPLOYMENT_ID=fixture-redis-settings-0001');
    $cacheDriver = new \app\modules\Platform\Config\Infrastructure\SystemRedisCache(['prefix' => 'lc:cache:']);
    $cacheOptions = (new \ReflectionProperty(\think\cache\driver\Redis::class, 'options'))->getValue($cacheDriver);
    $assert($cacheOptions['prefix'] === \app\modules\Platform\Config\Public\DeploymentNamespace::current()->cachePrefix(), 'Redis cache keys must use the site namespace');
    $assert($cacheOptions['tag_prefix'] === $cacheOptions['prefix'] . 'tag:', 'Redis cache tag keys must use the same site namespace');
    try {
        $cacheDriver->handler();
        throw new \RuntimeException('disabled system Redis cache must fail closed');
    } catch (\RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), '未启用'), 'cache must explain disabled system Redis');
    }

    Config::$values['redis']['enabled'] = true;
    Config::$values['redis']['host'] = 'localhost';
    Config::$values['redis']['password'] = 'sample-secret';
    putenv('LC_DEPLOYMENT_ID=');
    $assert(RedisConnectionSettings::test()['reason_code'] === 'deployment-identity-required', 'enabled Redis without identity must fail closed');
    putenv('LC_DEPLOYMENT_ID=fixture-redis-settings-0001');
    $database = RedisConnectionSettings::current();
    $assert($database['enabled'] === true && $database['password'] === 'sample-secret', 'database settings must reach PHP consumers');
    $assert($database['channel'] === \app\modules\Platform\Config\Public\DeploymentNamespace::current()->realtimeChannel(), 'Gateway and publisher must receive the same site channel');
    $assert($database['revision'] !== $disabled['revision'], 'revision must change with connection settings');
    $assert(!str_contains($database['revision'], 'sample-secret'), 'revision must not expose password');
    putenv('LC_DEPLOYMENT_ID=fixture-redis-settings-0002');
    $otherSite = RedisConnectionSettings::current();
    $assert($otherSite['channel'] !== $database['channel'] && $otherSite['revision'] !== $database['revision'], 'same Redis server must separate cloned sites');
    putenv('LC_DEPLOYMENT_ID=fixture-redis-settings-0001');

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
