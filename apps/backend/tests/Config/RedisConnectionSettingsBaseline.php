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

    Config::$values['redis'] = ['enabled' => false, 'host' => 'localhost', 'port' => 6379, 'database' => 0, 'password' => ''];
    $disabled = RedisConnectionSettings::current();
    $assert($disabled['enabled'] === false && $disabled['source'] === 'database', 'new install must leave Redis disabled');
    $assert(RedisConnectionSettings::test()['reason_code'] === 'redis-disabled', 'disabled Redis must not attempt connection');

    Config::$values['redis']['enabled'] = true;
    Config::$values['redis']['password'] = 'sample-secret';
    $database = RedisConnectionSettings::current();
    $assert($database['enabled'] === true && $database['password'] === 'sample-secret', 'database settings must reach PHP consumers');
    $assert($database['revision'] !== $disabled['revision'], 'revision must change with connection settings');
    $assert(!str_contains($database['revision'], 'sample-secret'), 'revision must not expose password');

    Env::$values['LC_REDIS_HOST'] = 'redis.internal';
    Env::$values['LC_REDIS_PORT'] = 6380;
    $legacy = RedisConnectionSettings::current();
    $assert($legacy['source'] === 'env' && $legacy['enabled'] === true, 'legacy environment connection remains explicit');
    $assert($legacy['host'] === 'redis.internal' && $legacy['port'] === 6380, 'environment connection overrides database group');
    $assert($legacy['revision'] !== $database['revision'], 'configuration source participates in revision');

    echo "Redis connection settings baseline passed.\n";
}
