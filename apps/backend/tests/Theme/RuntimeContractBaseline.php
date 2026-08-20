<?php

$root = dirname(__DIR__, 2);
$gateway = file_get_contents($root . '/app/modules/Theme/Runtime/ThemeGateway.php');
$manifest = file_get_contents($root . '/app/modules/Theme/Domain/ThemeManifest.php');
$appConfig = file_get_contents($root . '/config/app.php');
$frontendRoutes = file_get_contents($root . '/app/frontend/route/app.php');
require_once $root . '/app/modules/Theme/Domain/ThemeManifest.php';
$tests = [];

$tests['SPA bootstrap exposes stable runtime fields'] = static function () use ($gateway): void {
    foreach (['apiUrl', 'assetsBase', 'mode', 'release', 'config'] as $field) {
        if (strpos($gateway, "'{$field}'") === false) throw new RuntimeException("bootstrap field missing: {$field}");
    }
    if (strpos($gateway, 'lc-bootstrap') === false || strpos($gateway, 'application/json') === false) throw new RuntimeException('safe JSON bootstrap missing');
};
$tests['gateway uses SPA entry fallback and no route matcher'] = static function () use ($gateway): void {
    if (strpos($gateway, 'ThemeManifest::resolveEntry') === false || strpos($gateway, 'ThemeManifest::match') !== false) throw new RuntimeException('gateway is not entry based');
};
$tests['release resources are immutable and validated by host binding'] = static function () use ($gateway): void {
    foreach (['immutable', 'If-None-Match', 'ThemeBindingRepository', 'ASSET_PREFIX'] as $needle) if (strpos($gateway, $needle) === false) throw new RuntimeException("resource contract missing: {$needle}");
};
$tests['resource MIME contract includes modern web and media types'] = static function () use ($gateway): void {
    foreach (['wasm', 'avif', 'otf', 'mp4', 'webm', 'mp3', 'ogg'] as $extension) if (strpos($gateway, "'{$extension}'") === false) throw new RuntimeException("MIME missing: {$extension}");
};
$tests['manifest rejects the removed legacy engine protocol'] = static function () use ($manifest): void {
    if (strpos($manifest, "'engine'") === false || strpos($manifest, "'mode'") === false) throw new RuntimeException('manifest protocol guard missing');
};
$tests['stable media routes bypass SPA fallback without matching lookalikes'] = static function (): void {
    foreach (['/media', '/media/0123456789abcdef'] as $path) {
        if (!\app\modules\Theme\Domain\ThemeManifest::isReservedPath($path)) throw new RuntimeException("media path is not reserved: {$path}");
    }
    foreach (['/mediaevil', '/medial/0123456789abcdef'] as $path) {
        if (\app\modules\Theme\Domain\ThemeManifest::isReservedPath($path)) throw new RuntimeException("lookalike path was reserved: {$path}");
    }
};
$tests['multi-app dispatch preserves the stable media route'] = static function () use ($appConfig, $frontendRoutes): void {
    if (preg_match("/'media'\s*=>\s*'frontend'/", $appConfig) !== 1) throw new RuntimeException('media prefix is not mapped to the frontend app');
    foreach (['Route::get(":hash", "Media/get")', 'Route::head(":hash", "Media/get")'] as $route) {
        if (strpos($frontendRoutes, $route) === false) throw new RuntimeException("media application route missing: {$route}");
    }
};

$failures = 0;
foreach ($tests as $name => $test) {
    try { $test(); fwrite(STDOUT, "PASS {$name}\n"); }
    catch (Throwable $e) { $failures++; fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n"); }
}
if ($failures) exit(1);
fwrite(STDOUT, count($tests) . " Theme runtime contract tests passed.\n");
