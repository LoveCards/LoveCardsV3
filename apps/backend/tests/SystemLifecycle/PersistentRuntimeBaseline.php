<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require_once $root . '/bootstrap/ReleaseBootstrap.php';

use LoveCards\Bootstrap\ReleaseBootstrap;
use app\modules\Operations\Installer\Infrastructure\Common;
use app\modules\Operations\Installer\Infrastructure\Database;
use app\modules\Operations\Installer\Infrastructure\Rsa;
use app\modules\Operations\SystemLifecycle\Infrastructure\NativeReleaseRuntime;
use app\shared\SystemPaths\PersistentConfiguration;
use app\shared\SystemPaths\SystemPaths;

$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void { $tests[$name] = $callback; };
$assert = static function (bool $condition, string $message = 'assertion failed'): void {
    if (!$condition) throw new RuntimeException($message);
};
$assertThrows = static function (callable $callback, string $contains = '') use ($assert): void {
    try { $callback(); } catch (Throwable $exception) {
        if ($contains !== '') $assert(str_contains($exception->getMessage(), $contains), 'unexpected exception: ' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('expected exception was not thrown');
};

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lc-persistent-runtime-' . bin2hex(random_bytes(6));
if (!mkdir($tmp, 0700, true)) throw new RuntimeException('failed to create test root');
$cleanup = static function (string $directory) use (&$cleanup): void {
    if (!is_dir($directory) || is_link($directory)) { if (is_file($directory) || is_link($directory)) @unlink($directory); return; }
    foreach (scandir($directory) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (is_dir($path) && !is_link($path)) $cleanup($path); else @unlink($path);
    }
    @rmdir($directory);
};

$source = $tmp . DIRECTORY_SEPARATOR . 'source';
$instance = $tmp . DIRECTORY_SEPARATOR . 'instance';
mkdir($source, 0750, true);

$test('unconfigured legacy and development startup stays on current code', static function () use ($source, $assert): void {
    putenv('LC_INSTANCE_ROOT');
    putenv('LC_INSTANCE_ROOT_RESOLVED');
    putenv('LC_ACTIVE_RELEASE_ID=forged');
    $resolved = ReleaseBootstrap::resolve($source);
    $assert(realpath($resolved) === realpath($source), 'unmanaged startup left current code');
    $assert(getenv('LC_ACTIVE_RELEASE_ID') === false, 'unmanaged startup trusted a forged release marker');
});

$test('configured instance without a pointer supports first install and shared runtime', static function () use ($source, $instance, $assert): void {
    putenv('LC_INSTANCE_ROOT=' . $instance);
    $resolved = ReleaseBootstrap::resolve($source);
    $assert(realpath($resolved) === realpath($source), 'first install requires a release pointer');
    $runtime = getenv('LC_RUNTIME_ROOT');
    $logs = getenv('LC_LOG_ROOT');
    $assert(is_string($runtime) && is_dir($runtime), 'shared runtime was not prepared');
    $assert(is_string($logs) && is_dir($logs), 'shared logs were not prepared');
});

$test('default instance pointer remains stable after dispatch into releases', static function () use ($tmp, $assert): void {
    putenv('LC_INSTANCE_ROOT');
    $source = $tmp . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . 'backend';
    $instance = dirname($source) . DIRECTORY_SEPARATOR . '.lovecards-instance';
    $releaseId = 'release-default-1';
    $release = $instance . DIRECTORY_SEPARATOR . 'releases' . DIRECTORY_SEPARATOR . $releaseId;
    mkdir($source, 0750, true);
    mkdir($release, 0750, true);
    file_put_contents($release . DIRECTORY_SEPARATOR . 'release.json', json_encode(['release_id' => $releaseId], JSON_THROW_ON_ERROR));
    file_put_contents($instance . DIRECTORY_SEPARATOR . 'current', $releaseId . "\n");
    $resolved = ReleaseBootstrap::resolve($source);
    $assert(realpath($resolved) === realpath($release), 'default pointer did not resolve its release');
    $assert(getenv('LC_INSTANCE_ROOT_RESOLVED') === realpath($instance), 'resolved instance root was not exported for target config');
});

$test('bad, traversing and mismatched pointers fail closed', static function () use ($source, $instance, $assertThrows): void {
    putenv('LC_INSTANCE_ROOT=' . $instance);
    $releases = $instance . DIRECTORY_SEPARATOR . 'releases';
    if (!is_dir($releases)) mkdir($releases, 0750, true);
    file_put_contents($instance . DIRECTORY_SEPARATOR . 'current', "../escape\n");
    $assertThrows(static fn() => ReleaseBootstrap::resolve($source), '内容不合法');

    file_put_contents($instance . DIRECTORY_SEPARATOR . 'current', "missing-release\n");
    $assertThrows(static fn() => ReleaseBootstrap::resolve($source), '不存在');

    $release = $releases . DIRECTORY_SEPARATOR . 'release-mismatch';
    mkdir($release, 0750, true);
    file_put_contents($release . DIRECTORY_SEPARATOR . 'release.json', '{"release_id":"different"}');
    file_put_contents($instance . DIRECTORY_SEPARATOR . 'current', "release-mismatch\n");
    $assertThrows(static fn() => ReleaseBootstrap::resolve($source), '不一致');
});

$test('installer persists database, keys and lock without modifying active code', static function () use ($root, $tmp, $assert): void {
    $managed = $tmp . DIRECTORY_SEPARATOR . 'installer-instance';
    $paths = new SystemPaths($managed, $root);
    $tracked = [$root . '/config/database.php', $root . '/config/jwt.php'];
    $before = array_map(static fn(string $path): string => (string) hash_file('sha256', $path), $tracked);
    $password = "p'a\\ss\n\"<?php " . '$literal';

    $assert(Database::UpdataConfig('db.internal', 'love_cards', 'lc_user', 'first-value', '3307', $paths), 'initial database config write failed');
    $assert(Database::UpdataConfig('db.internal', 'love_cards', 'lc_user', $password, '3307', $paths), 'atomic database config replacement failed');
    // The helper reads the environment-selected instance; point it at the fixture.
    putenv('LC_INSTANCE_ROOT=' . $managed);
    putenv('LC_INSTANCE_ROOT_RESOLVED=' . $managed);
    $database = PersistentConfiguration::database($root);
    $assert($database['password'] === $password, 'special database password did not round-trip');
    $assert($database['charset'] === 'utf8mb4', 'persistent database charset drifted');

    $assert(Rsa::UpdataRsa("PUBLIC\n", "PRIVATE\n", $paths), 'persistent RSA write failed');
    $assert(file_get_contents($paths->keysRoot() . '/private.pem') === "PRIVATE\n", 'private key missing');
    $assert(file_get_contents($paths->keysRoot() . '/public.pem') === "PUBLIC\n", 'public key missing');
    $assert(Common::InstallLock($paths), 'persistent install lock failed');
    $assert(Common::CheckInstallLock($paths), 'persistent install lock was not detected');
    $assert(is_file($paths->stateRoot() . '/install.lock'), 'install lock is outside shared state');

    $after = array_map(static fn(string $path): string => (string) hash_file('sha256', $path), $tracked);
    $assert($before === $after, 'installer modified active release configuration');
    $leftovers = glob($paths->configRoot() . '/*.next.*') ?: [];
    $assert($leftovers === [], 'atomic config temporary file was left behind');
});

$test('ThinkPHP config consumes persistent database and JWT state', static function () use ($root, $tmp, $assert): void {
    $managed = $tmp . DIRECTORY_SEPARATOR . 'installer-instance';
    putenv('LC_INSTANCE_ROOT=' . $managed);
    putenv('LC_INSTANCE_ROOT_RESOLVED=' . $managed);
    $app = new \think\App($root);
    $app->setRuntimePath($tmp . DIRECTORY_SEPARATOR . 'think-runtime' . DIRECTORY_SEPARATOR);
    // 隔离：开发机的 apps/backend 下可能存在运行时 .env（会进 Env 最高优先级）。
    // 本用例验证的是 "env 未设时持久实例配置回退生效"，不能被宿主机 .env 劫持；
    // setEnvName 指向不存在的 .env.<marker>，让 loadEnv 跳过 .env 本体。
    $app->setEnvName('lc-tests-isolated');
    $app->initialize();
    $database = $app->config->get('database.connections.mysql');
    $jwt = $app->config->get('jwt');
    $assert(($database['hostname'] ?? null) === 'db.internal', 'persistent database hostname was not loaded');
    $assert(($database['password'] ?? null) === "p'a\\ss\n\"<?php " . '$literal', 'persistent database password changed in config');
    $assert(($jwt['privateKey'] ?? null) === $managed . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'keys' . DIRECTORY_SEPARATOR . 'private.pem', 'persistent JWT private key was not selected');
    $assert(($jwt['publicKey'] ?? null) === $managed . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'keys' . DIRECTORY_SEPARATOR . 'public.pem', 'persistent JWT public key was not selected');
});

$test('runtime capabilities are trimmed by configuration and commands bypass a shell', static function () use ($tmp, $assert): void {
    $marker = $tmp . DIRECTORY_SEPARATOR . 'worker marker.txt';
    $runtime = new NativeReleaseRuntime([
        'drain_command' => [PHP_BINARY, '-r', 'file_put_contents($argv[1], $argv[2]);', $marker, '{release_id}'],
        'reload_command' => '[]',
        'command_timeout_seconds' => 10,
    ], 'cli');
    $assert($runtime->canDrain(), 'configured drain capability is missing');
    $assert(!$runtime->canReload(), 'unconfigured reload capability was enabled');
    $runtime->drain('release-worker-1');
    $assert(file_get_contents($marker) === 'release-worker-1', 'release placeholder was not passed as one argv value');
});

$test('FPM health requires the request to be served by the target release', static function () use ($assert, $assertThrows): void {
    putenv('LC_ACTIVE_RELEASE_ID=release-health-1');
    $runtime = new NativeReleaseRuntime([], 'fpm-fcgi');
    $runtime->healthCheck('release-health-1');
    $assertThrows(static fn() => $runtime->healthCheck('release-health-2'), '尚未切换');
    $assert(ReleaseBootstrap::isHealthRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/_lc/release-health']), 'health endpoint was not recognized');
});

$test('valid pointer dispatches Web and CLI through the immutable release once', static function () use ($source, $instance, $assert): void {
    putenv('LC_INSTANCE_ROOT=' . $instance);
    $releaseId = 'release-valid-001';
    $release = $instance . DIRECTORY_SEPARATOR . 'releases' . DIRECTORY_SEPARATOR . $releaseId;
    if (!is_dir($release . DIRECTORY_SEPARATOR . 'public')) mkdir($release . DIRECTORY_SEPARATOR . 'public', 0750, true);
    file_put_contents($release . DIRECTORY_SEPARATOR . 'release.json', json_encode(['release_id' => $releaseId], JSON_THROW_ON_ERROR));
    file_put_contents($release . DIRECTORY_SEPARATOR . 'public/index.php', "<?php\n");
    file_put_contents($release . DIRECTORY_SEPARATOR . 'think', "<?php\n");
    file_put_contents($instance . DIRECTORY_SEPARATOR . 'current', $releaseId . "\n");

    $entry = ReleaseBootstrap::webEntry($source);
    $assert(is_string($entry) && realpath($entry) === realpath($release . '/public/index.php'), 'Web entry did not dispatch target release');
    $assert(getenv('LC_ACTIVE_RELEASE_ID') === $releaseId, 'active release marker was not exported');
    $assert(ReleaseBootstrap::RELEASE_HEADER === 'X-LoveCards-Release-Id', 'public release response header drifted');
    $assert(realpath(ReleaseBootstrap::resolve($release)) === realpath($release), 'dispatch recursion guard rejected its target');
});

$failures = 0;
try {
    foreach ($tests as $name => $callback) {
        try { $callback(); fwrite(STDOUT, "PASS {$name}\n"); }
        catch (Throwable $exception) { $failures++; fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n"); }
    }
} finally {
    putenv('LC_INSTANCE_ROOT');
    putenv('LC_INSTANCE_ROOT_RESOLVED');
    putenv('LC_ACTIVE_RELEASE_ID');
    putenv('LC_RUNTIME_ROOT');
    putenv('LC_LOG_ROOT');
    $cleanup($tmp);
}
if ($failures > 0) exit(1);
fwrite(STDOUT, count($tests) . " Persistent runtime tests passed.\n");
