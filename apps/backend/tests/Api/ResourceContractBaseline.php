<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

require $root . '/app/shared/Transport/Http/Resource.php';
require $root . '/app/shared/Transport/Http/PublicResource.php';
require $root . '/app/shared/Transport/Http/ApiResource.php';
require $root . '/app/modules/Content/Cards/Transport/Api/CardResource.php';
require $root . '/app/modules/Platform/Rbac/Transport/Api/RoleResource.php';
require $root . '/app/modules/Platform/Rbac/Transport/Api/PermissionResource.php';
require $root . '/app/modules/Platform/Files/Transport/Api/FileResource.php';
require $root . '/app/modules/Platform/Files/Transport/Api/DirectUploadResource.php';
require $root . '/app/modules/Content/Likes/Transport/Api/LikeResource.php';
require $root . '/app/modules/Operations/Dashboard/Transport/Api/DashboardResource.php';
require $root . '/app/modules/Operations/Version/Transport/Api/SystemResource.php';
require $root . '/app/modules/Platform/Auth/Transport/Api/AuthResource.php';
require $root . '/app/modules/Platform/Config/Transport/Api/ConfigResource.php';
require $root . '/app/modules/Platform/Captcha/Transport/Api/CaptchaResource.php';
require $root . '/app/modules/Platform/Sender/Transport/Api/SenderResource.php';
require $root . '/app/modules/Platform/Storage/Transport/Api/StorageResource.php';
require $root . '/app/modules/Theme/Transport/Api/ThemeResource.php';
require $root . '/app/modules/Platform/User/Transport/Api/ProfileResource.php';

use app\shared\Transport\Http\ApiResource;
use app\modules\Platform\Auth\Transport\Api\AuthResource;
use app\modules\Content\Cards\Transport\Api\CardResource;
use app\modules\Platform\Config\Transport\Api\ConfigResource;
use app\modules\Platform\Files\Transport\Api\DirectUploadResource;
use app\modules\Platform\Files\Transport\Api\FileResource;
use app\modules\Content\Likes\Transport\Api\LikeResource;
use app\modules\Platform\Rbac\Transport\Api\PermissionResource;
use app\modules\Platform\Rbac\Transport\Api\RoleResource;

$tests = [];

$test = static function (string $name, Closure $callback) use (&$tests): void {
    $tests[$name] = $callback;
};

$assertSame = static function ($expected, $actual, string $message = ''): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            ($message !== '' ? $message . ': ' : '')
            . 'expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
};

$assertFalse = static function (bool $value, string $message = ''): void {
    if ($value !== false) {
        throw new RuntimeException($message !== '' ? $message : 'expected false, got true');
    }
};

$test('card resource decodes json fields and exposes the public card contract', static function () use ($assertSame, $assertFalse): void {
    $result = CardResource::make([
        'id' => 10,
        'data' => '{"title":"hello"}',
        'tags' => '["a","b"]',
        'pictures' => '["/a.jpg"]',
        'password' => 'secret',
        'deleted_at' => '2026-01-01',
    ]);

    $assertSame(['title' => 'hello'], $result['data']);
    $assertSame(['a', 'b'], $result['tags']);
    $assertSame(['/a.jpg'], $result['pictures']);
    $assertFalse(array_key_exists('password', $result));
    $assertFalse(array_key_exists('deleted_at', $result));
});

$test('role pagination maps each row through role resource', static function () use ($assertSame, $assertFalse): void {
    $result = RoleResource::paginate([
        'current_page' => 1,
        'data' => [[
            'id' => 1,
            'name' => 'Admin',
            'slug' => 'admin',
            'password' => 'secret',
        ]],
        'total' => 1,
    ]);

    $assertSame(1, $result['current_page']);
    $assertSame('admin', $result['data'][0]['slug']);
    $assertFalse(array_key_exists('password', $result['data'][0]));
});

$test('permission resource keeps the capability tuple stable', static function () use ($assertSame): void {
    $result = PermissionResource::make(['capability' => 'cards.read', 'description' => 'Read cards', 'extra' => true]);

    $assertSame(['capability' => 'cards.read', 'description' => 'Read cards'], $result);
});

$test('file resource removes storage internals but keeps public upload fields', static function () use ($assertSame, $assertFalse): void {
    $result = FileResource::make([
        'id' => 8,
        'url' => '/upload/a.png',
        'driver_path' => 'private/key',
        'deleted_at' => '2026-01-01',
    ]);

    $assertSame(8, $result['id']);
    $assertSame('/upload/a.png', $result['url']);
    $assertFalse(array_key_exists('driver_path', $result));
    $assertFalse(array_key_exists('deleted_at', $result));
});

$test('direct upload resource exposes credential fields only', static function () use ($assertSame, $assertFalse): void {
    $result = DirectUploadResource::make([
        'record_id' => 9,
        'upload_url' => 'https://example.test/upload',
        'method' => 'PUT',
        'headers' => ['x-test' => '1'],
        'form_data' => [],
        'expire' => 3600,
        'secret' => 'nope',
    ]);

    $assertSame(9, $result['record_id']);
    $assertFalse(array_key_exists('secret', $result));
});

$test('like resource hides ip addresses', static function () use ($assertSame, $assertFalse): void {
    $result = LikeResource::make(['id' => 1, 'pid' => 2, 'uid' => 3, 'ip' => '127.0.0.1']);

    $assertSame(2, $result['pid']);
    $assertFalse(array_key_exists('ip', $result));
});

$test('auth resource only returns token', static function () use ($assertSame): void {
    $assertSame(['token' => 'abc'], AuthResource::make(['token' => 'abc', 'uid' => 1]));
});

$test('api resource preserves simple command result arrays', static function () use ($assertSame): void {
    $assertSame(['id' => 123], ApiResource::make(['id' => 123]));
    $assertSame(['cards.read', 'cards.create'], ApiResource::make(['cards.read', 'cards.create']));
});

$test('config resource preserves dynamic config groups', static function () use ($assertSame): void {
    $result = ConfigResource::make(['frontend' => ['active_theme' => 'default-ssr']]);

    $assertSame('default-ssr', $result['frontend']['active_theme']);
});

$failures = 0;
foreach ($tests as $name => $callback) {
    try {
        $callback();
        fwrite(STDOUT, "PASS {$name}\n");
    } catch (Throwable $exception) {
        $failures++;
        fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
    }
}

if ($failures > 0) {
    fwrite(STDERR, "{$failures} resource contract baseline test(s) failed.\n");
    exit(1);
}

fwrite(STDOUT, count($tests) . " resource contract baseline tests passed.\n");
