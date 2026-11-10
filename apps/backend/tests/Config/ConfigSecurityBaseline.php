<?php
declare(strict_types=1);

namespace app\modules\Platform\Config\Public {
    final class Config
    {
        public static array $groups = [];
        public static array $values = [];
        public static array $schemas = [];
        public static array $environmentManaged = [];
        public static array $writes = [];

        public static function reset(): void
        {
            self::$groups = ['core', 'storage', 'storage_cos', 'sender_smtp', 'captcha_geetest_v4', 'redis'];
            self::$values = [
                'core' => ['name' => 'LoveCards'],
                'storage' => ['default' => 'cos'],
                'storage_cos' => ['bucket' => 'assets', 'secret_id' => 'id-value', 'secret_key' => 'key-value'],
                'sender_smtp' => ['host' => 'smtp.example.test', 'pass' => 'mail-secret'],
                'captcha_geetest_v4' => ['id' => 'captcha-id', 'key' => 'captcha-secret'],
                'redis' => ['enabled' => false, 'host' => '127.0.0.1', 'port' => 6379, 'database' => 0, 'password' => 'redis-secret'],
            ];
            self::$schemas = [
                'core' => ['name' => ['type' => 'string']],
                'storage' => ['default' => ['type' => 'string']],
                'storage_cos' => [
                    'bucket' => ['type' => 'string'],
                    'secret_id' => ['type' => 'string'],
                    'secret_key' => ['type' => 'string'],
                ],
                'sender_smtp' => [
                    'host' => ['type' => 'string'],
                    'pass' => ['type' => 'string'],
                ],
                'captcha_geetest_v4' => [
                    'id' => ['type' => 'string'],
                    'key' => ['type' => 'string'],
                ],
                'redis' => [
                    'enabled' => ['type' => 'bool'], 'host' => ['type' => 'string'],
                    'port' => ['type' => 'int'], 'database' => ['type' => 'int'],
                    'password' => ['type' => 'password', 'sensitive' => true],
                ],
            ];
            self::$environmentManaged = [];
            self::$writes = [];
        }

        public static function getSchemaGroups(): array { return self::$groups; }
        public static function getGroup(string $group): array { return self::$values[$group] ?? []; }
        public static function getSchema(string $group): array { return self::$schemas[$group] ?? []; }
        public static function setGroup(string $group, array $config): bool
        {
            self::$writes[] = [$group, $config];
            self::$values[$group] = array_merge(self::$values[$group] ?? [], $config);
            return true;
        }
        public static function isEnvironmentManaged(string $group, string $key): bool
        {
            return self::$environmentManaged["{$group}.{$key}"] ?? false;
        }
        public static function init(): array { return []; }
        public static function register(string $group, array $schema): array { return ['group' => $group, 'schema' => $schema]; }
        public static function reload(?string $group): void {}
        public static function deleteGroup(string $group): bool { return true; }
        public static function deleteKey(string $group, string $key): bool { return true; }
    }
}

namespace app\modules\Platform\Captcha\Public {
    final class Captcha
    {
        public static bool $ready = false;
        public static ?string $readyDriver = null;

        public static function isReady(string $type, ?string $driverSlug = null): bool
        {
            return $type === 'captcha' && self::$ready
                && (self::$readyDriver === null || self::$readyDriver === $driverSlug);
        }
    }
}

namespace {
    $root = dirname(__DIR__, 2);
    require $root . '/app/shared/Authorization/Scope.php';
    require $root . '/app/shared/Authorization/Grant.php';
    require $root . '/app/shared/Authorization/GrantSet.php';
    require $root . '/app/api/ApiException.php';
    require $root . '/app/modules/Platform/Config/Service/ConfigAccessPolicy.php';
    require $root . '/app/modules/Platform/Config/Service/ConfigManager.php';

    use app\api\ApiException;
    use app\modules\Platform\Config\Public\Config;
    use app\modules\Platform\Config\Service\ConfigManager;
    use app\modules\Platform\Captcha\Public\Captcha;
    use app\shared\Authorization\Grant;
    use app\shared\Authorization\GrantSet;
    use app\shared\Authorization\Scope;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    };
    $assertSame = static function ($expected, $actual, string $message): void {
        if ($expected !== $actual) {
            throw new \RuntimeException($message . '; expected=' . var_export($expected, true) . ', actual=' . var_export($actual, true));
        }
    };
    $grants = static function (string ...$capabilities): GrantSet {
        return new GrantSet(array_map(static fn(string $capability): Grant => new Grant($capability, Scope::GLOBAL), $capabilities));
    };
    $expectApi = static function (int $status, \Closure $callback, string $message) use ($assertSame): void {
        try {
            $callback();
        } catch (ApiException $exception) {
            $assertSame($status, $exception->getHttpStatus(), $message);
            return;
        }
        throw new \RuntimeException($message . '; expected ApiException');
    };

    $tests = [];
    $test = static function (string $name, \Closure $callback) use (&$tests): void { $tests[$name] = $callback; };

    $test('implicit list returns only actor-readable groups', static function () use ($grants, $assertSame): void {
        Config::reset();
        $manager = new ConfigManager();
        $result = $manager->get([], $grants('admin.access', 'config.read'));
        $assertSame(['core'], array_keys($result), 'base config reader must not receive infrastructure groups');

        $storageResult = $manager->get([], $grants('admin.access', 'config.read', 'storage.read'));
        $assertSame(['core', 'storage', 'storage_cos'], array_keys($storageResult), 'storage reader should receive only storage groups');
        $assertSame(['core', 'storage', 'storage_cos'], $manager->schemaGroups($grants('admin.access', 'config.read', 'storage.read')), 'group names must be filtered too');
    });

    $test('explicit list rejects every narrow group outside the actor domain', static function () use ($grants, $expectApi): void {
        Config::reset();
        $manager = new ConfigManager();
        foreach ([
            'storage_cos' => 'storage.read',
            'sender_smtp' => 'sender.read',
            'captcha_geetest_v4' => 'captcha.read',
            'redis' => 'operations.runtime.configure',
        ] as $group => $capability) {
            $expectApi(
                403,
                static fn() => $manager->get([$group], $grants('admin.access', 'config.read')),
                "explicit {$group} read must require {$capability}"
            );
        }
    });

    $test('update rejects a group outside the actor domain before writing', static function () use ($grants, $expectApi, $assertSame): void {
        Config::reset();
        $manager = new ConfigManager();
        $expectApi(403, static fn() => $manager->update([
            'core' => ['name' => 'Changed before denial'],
            'storage' => ['default' => 'local'],
        ], $grants('admin.access', 'config.update')), 'storage update must require storage.read');
        $assertSame([], Config::$writes, 'unauthorized update must not write');

        foreach ([
            'sender_smtp' => ['host' => 'changed.example.test'],
            'captcha_geetest_v4' => ['id' => 'changed-id'],
            'redis' => ['host' => 'changed.example.test'],
        ] as $group => $payload) {
            $expectApi(
                403,
                static fn() => $manager->update([$group => $payload], $grants('admin.access', 'config.update')),
                "{$group} update must require its domain capability"
            );
        }
        $assertSame([], Config::$writes, 'all unauthorized narrow-domain updates must fail before writing');
    });

    $test('base config capabilities are enforced inside the service', static function () use ($grants, $expectApi): void {
        Config::reset();
        $manager = new ConfigManager();
        $expectApi(403, static fn() => $manager->get([], $grants('admin.access', 'storage.read')), 'config.read must be required by the service');
        $expectApi(403, static fn() => $manager->update(['core' => ['name' => 'Denied']], $grants('admin.access')), 'config.update must be required by the service');
        $expectApi(403, static fn() => $manager->get([], $grants('config.read')), 'admin.access must be required by the service');
    });

    $test('sensitive values are replaced by state-only markers', static function () use ($grants, $assert, $assertSame): void {
        Config::reset();
        $manager = new ConfigManager();
        $result = $manager->get(['storage_cos', 'sender_smtp', 'captcha_geetest_v4'], $grants(
            'admin.access', 'config.read', 'storage.read', 'sender.read', 'captcha.read'
        ));

        $assertSame(['$secret' => 'keep', 'configured' => true], $result['storage_cos']['secret_key'], 'storage secret must be redacted');
        $assertSame(['$secret' => 'keep', 'configured' => true], $result['storage_cos']['secret_id'], 'credential identifiers must be redacted');
        $assertSame(['$secret' => 'keep', 'configured' => true], $result['sender_smtp']['pass'], 'sender password must be redacted');
        $assertSame(['$secret' => 'keep', 'configured' => true], $result['captcha_geetest_v4']['key'], 'captcha key must be redacted');
        $assert($result['storage_cos']['bucket'] === 'assets', 'non-sensitive values must remain readable');
        $assert(!str_contains(json_encode($result, JSON_THROW_ON_ERROR), 'key-value'), 'serialized response must not contain storage secret');
        $assert(!str_contains(json_encode($result, JSON_THROW_ON_ERROR), 'mail-secret'), 'serialized response must not contain sender secret');
    });

    $test('redis settings require runtime permission and mask credentials', static function () use ($grants, $assertSame, $expectApi): void {
        Config::reset();
        $manager = new ConfigManager();
        $granted = $grants('admin.access', 'config.read', 'config.update', 'operations.runtime.configure');
        $result = $manager->get(['redis'], $granted);
        $assertSame(['$secret' => 'keep', 'configured' => true], $result['redis']['password'], 'redis password must be redacted');
        $expectApi(403, static fn() => $manager->get(['redis'], $grants('admin.access', 'config.read')), 'redis group must require runtime permission');
        $manager->update(['redis' => ['enabled' => true, 'host' => 'redis.example.test', 'port' => 6380]], $granted);
        $assertSame([['redis', ['enabled' => true, 'host' => 'redis.example.test', 'port' => 6380]]], Config::$writes, 'valid redis update should use existing config path');
        $expectApi(400, static fn() => $manager->deleteGroup('redis', $grants('admin.access', 'config.deleteKey', 'operations.runtime.configure')), 'built-in redis group cannot be deleted');
    });

    $test('secret keep replace and clear have explicit behavior', static function () use ($grants, $assertSame): void {
        Config::reset();
        $manager = new ConfigManager();
        $writeGrants = $grants('admin.access', 'config.update', 'storage.read');

        $manager->update(['storage_cos' => [
            'bucket' => 'new-assets',
            'secret_key' => ['$secret' => 'keep'],
            'secret_id' => '',
        ]], $writeGrants);
        $assertSame([['storage_cos', ['bucket' => 'new-assets']]], Config::$writes, 'keep operations must never persist redacted values');

        Config::$writes = [];
        $manager->update(['storage_cos' => [
            'secret_key' => ['$secret' => 'replace', 'value' => 'new-key'],
            'secret_id' => ['$secret' => 'clear'],
        ]], $writeGrants);
        $assertSame([['storage_cos', ['secret_key' => 'new-key', 'secret_id' => '']]], Config::$writes, 'replace and clear must persist only explicit values');
    });

    $test('raw secret replacement and environment-managed writes fail closed', static function () use ($grants, $expectApi, $assertSame): void {
        Config::reset();
        $manager = new ConfigManager();
        $writeGrants = $grants('admin.access', 'config.update', 'storage.read');
        $expectApi(400, static fn() => $manager->update(['storage_cos' => ['secret_key' => 'raw-secret']], $writeGrants), 'raw secret replacement must be rejected');
        $assertSame([], Config::$writes, 'invalid raw secret must not write');

        Config::$environmentManaged['storage_cos.secret_key'] = true;
        $expectApi(400, static fn() => $manager->update(['storage_cos' => ['secret_key' => ['$secret' => 'clear']]], $writeGrants), 'environment-managed secret cannot be cleared in the database');
        $assertSame([], Config::$writes, 'environment-managed secret update must not write');
    });

    $test('human verification requires a ready driver before enabling', static function () use ($grants, $expectApi, $assertSame): void {
        Config::reset();
        Config::$groups[] = 'captcha';
        Config::$schemas['captcha'] = ['captcha_enabled' => ['type' => 'bool']];
        $manager = new ConfigManager();
        $writeGrants = $grants('admin.access', 'config.update', 'captcha.read');

        Captcha::$ready = false;
        Captcha::$readyDriver = null;
        $expectApi(422, static fn() => $manager->update(['captcha' => ['captcha_enabled' => true]], $writeGrants), 'unready human verification must be rejected');
        $assertSame([], Config::$writes, 'unready driver must not persist enabled policy');

        $manager->update(['captcha' => ['captcha_enabled' => false]], $writeGrants);
        $assertSame([['captcha', ['captcha_enabled' => false]]], Config::$writes, 'operator must be able to disable an unready policy');

        Config::$writes = [];
        Captcha::$ready = true;
        $manager->update(['captcha' => ['captcha_enabled' => true]], $writeGrants);
        $assertSame([['captcha', ['captcha_enabled' => true]]], Config::$writes, 'ready driver permits enabling');
    });

    $test('enabled policy rejects switching to an unready driver', static function () use ($grants, $expectApi, $assertSame): void {
        Config::reset();
        Config::$groups[] = 'captcha';
        Config::$schemas['captcha'] = [
            'captcha_enabled' => ['type' => 'bool'],
            'default_captcha_driver' => ['type' => 'string'],
        ];
        Config::$values['captcha'] = ['captcha_enabled' => true, 'default_captcha_driver' => 'geetest_v4'];
        Captcha::$ready = true;
        Captcha::$readyDriver = 'geetest_v4';
        $manager = new ConfigManager();
        $writeGrants = $grants('admin.access', 'config.update', 'captcha.read');

        $expectApi(422, static fn() => $manager->update(['captcha' => ['default_captcha_driver' => 'gd_image']], $writeGrants), 'active policy cannot switch to unready image driver');
        $assertSame([], Config::$writes, 'invalid driver switch must not persist');

        Config::$values['captcha']['captcha_enabled'] = false;
        $manager->update(['captcha' => ['default_captcha_driver' => 'gd_image']], $writeGrants);
        $assertSame([['captcha', ['default_captcha_driver' => 'gd_image']]], Config::$writes, 'disabled policy may select driver before setup');
    });

    $test('fresh install leaves human verification disabled', static function () use ($assertSame): void {
        $schema = require dirname(__DIR__, 2) . '/config/apps/captcha.php';
        $assertSame(false, $schema['captcha_enabled']['default'] ?? null, 'fresh install must not require an unconfigured challenge');
    });

    $failures = 0;
    foreach ($tests as $name => $callback) {
        try {
            $callback();
            fwrite(STDOUT, "PASS {$name}\n");
        } catch (\Throwable $exception) {
            $failures++;
            fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
        }
    }
    if ($failures > 0) exit(1);
    fwrite(STDOUT, count($tests) . " config security baseline tests passed.\n");
}
