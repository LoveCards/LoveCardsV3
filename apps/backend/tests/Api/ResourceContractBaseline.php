<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

require $root . '/app/shared/Transport/Http/Resource.php';
require $root . '/app/shared/Transport/Http/PublicResource.php';
require $root . '/app/shared/Transport/Http/ApiResource.php';
require $root . '/app/modules/Content/Cards/Transport/Api/CardResource.php';
require $root . '/app/modules/Content/Cards/Transport/Api/CardCreatedResource.php';
require $root . '/app/modules/Content/Likes/Transport/Api/LikeCountResource.php';
require $root . '/app/modules/Content/Comments/Transport/Api/CommentCreatedResource.php';
require $root . '/app/modules/Content/Comments/Transport/Api/CommentResource.php';
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
require $root . '/app/modules/Theme/Transport/Api/ThemeConfigResource.php';
require $root . '/app/modules/Platform/User/Transport/Api/ProfileResource.php';

use app\shared\Transport\Http\ApiResource;
use app\modules\Platform\Auth\Transport\Api\AuthResource;
use app\modules\Content\Cards\Transport\Api\CardResource;
use app\modules\Content\Cards\Transport\Api\CardCreatedResource;
use app\modules\Content\Comments\Transport\Api\CommentCreatedResource;
use app\modules\Content\Comments\Transport\Api\CommentResource;
use app\modules\Content\Likes\Transport\Api\LikeCountResource;
use app\modules\Platform\Config\Transport\Api\ConfigResource;
use app\modules\Platform\Files\Transport\Api\DirectUploadResource;
use app\modules\Platform\Files\Transport\Api\FileResource;
use app\modules\Content\Likes\Transport\Api\LikeResource;
use app\modules\Operations\Dashboard\Transport\Api\DashboardResource;
use app\modules\Platform\Rbac\Transport\Api\PermissionResource;
use app\modules\Platform\Rbac\Transport\Api\RoleResource;
use app\modules\Theme\Transport\Api\ThemeResource;
use app\modules\Theme\Transport\Api\ThemeConfigResource;

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
        'username' => '卡卡旅行记',
        'data' => '{"title":"hello"}',
        'tags' => '[13,14]',
        'pictures' => '["/a.jpg"]',
        'media' => '[]',
        'password' => 'secret',
        'deleted_at' => '2026-01-01',
    ]);

    $assertSame(['title' => 'hello'], $result['data']);
    $assertSame([13, 14], $result['tags']);
    $assertSame(['/a.jpg'], $result['pictures']);
    $assertSame([], $result['media']);
    $assertSame('卡卡旅行记', $result['username']);
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
    $result = PermissionResource::make(['capability' => 'cards.read', 'scope' => 'own', 'description' => 'Read cards', 'extra' => true]);

    $assertSame(['capability' => 'cards.read', 'scope' => 'own', 'description' => 'Read cards'], $result);
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

$test('like resource exposes the typed target but hides ip addresses', static function () use ($assertSame, $assertFalse): void {
    $result = LikeResource::make(['id' => 1, 'pid' => 2, 'ref_type' => 'comment', 'ref_id' => 2, 'uid' => 3, 'ip' => '127.0.0.1']);

    $assertSame(2, $result['pid']);
    $assertSame('comment', $result['ref_type']);
    $assertSame(2, $result['ref_id']);
    $assertFalse(array_key_exists('ip', $result));
});

$test('auth resource only returns token', static function () use ($assertSame): void {
    $assertSame(['token' => 'abc'], AuthResource::make(['token' => 'abc', 'uid' => 1]));
});

$test('api resource preserves simple command result arrays', static function () use ($assertSame): void {
    $assertSame(['id' => 123], ApiResource::make(['id' => 123]));
    $assertSame(['cards.read', 'cards.create'], ApiResource::make(['cards.read', 'cards.create']));
});

$test('comment resource exposes a valid author username and like count', static function () use ($assertSame): void {
    $result = CommentResource::make(['id' => 4, 'pid' => 9, 'user_id' => 2, 'username' => '评论作者', 'content' => '正文', 'status' => 0, 'goods' => 3]);
    $assertSame('评论作者', $result['username']);
    $assertSame(3, $result['goods']);
});

$test('command resources expose stable fields only', static function () use ($assertSame, $assertFalse): void {
    $assertSame(['id' => 7, 'status' => 1], CardCreatedResource::make(['id' => 7, 'status' => 1, 'secret' => 'x']));
    $assertSame(['likes' => 3], LikeCountResource::make(['likes' => 3, 'ip' => '127.0.0.1']));
    $assertSame(['id' => 8, 'status' => 0], CommentCreatedResource::make(['id' => 8, 'status' => 0, 'password' => 'x']));
    $assertFalse((CardCreatedResource::schema()['additionalProperties'] ?? true));
});

$test('dashboard resource exposes capability-filtered summaries and typed workbench tasks', static function () use ($assertSame, $assertFalse): void {
    $result = DashboardResource::make([
        'count' => ['cards' => 2],
        'chart' => [],
        'ver' => ['app_name' => 'LoveCards'],
        'notice' => [],
        'workbench' => ['tasks' => [[
            'key' => 'cards_pending',
            'label' => '待审核卡片',
            'description' => '检查等待发布的卡片内容。',
            'count' => 1,
            'action_url' => '/apps/content/cards',
            'severity' => 'warning',
        ]]],
        'secret' => 'nope',
    ]);

    $assertSame(['cards' => 2], $result['count']);
    $assertSame('cards_pending', $result['workbench']['tasks'][0]['key']);
    $assertFalse(array_key_exists('secret', $result));
    $schema = DashboardResource::schema();
    $assertSame('array', $schema['properties']['chart']['type']);
    $assertSame('object', $schema['properties']['ver']['type']);
    $assertSame('array', $schema['properties']['workbench']['properties']['tasks']['type']);
});

$test('config resource preserves dynamic config groups', static function () use ($assertSame): void {
    $result = ConfigResource::make(['frontend' => ['active_theme' => 'default']]);

    $assertSame('default', $result['frontend']['active_theme']);
});

$test('theme resources expose only the SPA release contract', static function () use ($assertSame, $assertFalse): void {
    $item = ThemeResource::make(['id' => 'default', 'name' => 'Default', 'version' => '1.0.0', 'mode' => 'spa', 'release' => '1.0.0-abcd1234', 'author' => 'LoveCards', 'description' => '', 'active' => true, 'engine' => 'html']);
    $assertSame('spa', $item['mode']);
    $assertFalse(array_key_exists('engine', $item));
    $config = ThemeConfigResource::make(['theme' => ['id' => 'default', 'name' => 'Default', 'version' => '1.0.0', 'mode' => 'spa', 'release' => '1.0.0-abcd1234'], 'config_schema' => [], 'config_values' => []]);
    $assertSame('default', $config['id']);
    $assertSame('1.0.0-abcd1234', $config['release']);
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
