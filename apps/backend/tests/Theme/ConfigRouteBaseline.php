<?php

$root = dirname(__DIR__, 2);
$route = file_get_contents($root . '/app/api/route/theme.php');
$themeBoot = file_get_contents($root . '/app/frontend/middleware/ThemeBoot.php');
$sdk = file_get_contents(dirname($root, 2) . '/packages/sdk/src/generated/resources/theme.ts');
$dataSql = file_get_contents($root . '/data.sql');
$tests = [];

$tests['GET theme/config is registered exactly once'] = static function () use ($route): void {
    if (preg_match_all("/Route::get\\(['\"]theme\\/config['\"]/", $route) !== 1) {
        throw new RuntimeException('GET /theme/config 必须且只能注册一次');
    }
};
$tests['GET theme/config remains public'] = static function () use ($route): void {
    if (strpos($route, "->name('theme.publicConfig')") === false || strpos($route, "'access' => ['auth' => 'public']") === false) {
        throw new RuntimeException('公开主题配置契约丢失');
    }
};
$tests['protected duplicate route name is removed'] = static function () use ($route): void {
    if (strpos($route, "->name('theme.config')") !== false) {
        throw new RuntimeException('仍存在重复的受保护 GET route');
    }
};
$tests['PUT theme/config remains protected'] = static function () use ($route): void {
    if (strpos($route, "Route::put('config', 'Theme.ThemeManager/updateConfig')") === false || strpos($route, "['capability' => 'theme.update', 'scope' => 'global']") === false) {
        throw new RuntimeException('PUT 主题配置契约被改变');
    }
};
$tests['SDK compatibility methods retain the same path'] = static function () use ($sdk): void {
    if (preg_match_all("/_get<ApiTypes\\.ThemeConfigData>\\([\"']\\/theme\\/config[\"']\\)/", $sdk) !== 2) {
        throw new RuntimeException('SDK config/publicConfig 路径发生变化');
    }
};
$tests['SDK theme mutations use id and release'] = static function () use ($sdk): void {
    if (strpos($sdk, 'activate(data: { id: string; release: string })') === false || strpos($sdk, 'updateConfig(data: { id: string; release: string; values: Record<string, unknown> })') === false) {
        throw new RuntimeException('SDK 主题 Release 参数契约丢失');
    }
};
$tests['Theme middleware bypasses the system installer'] = static function () use ($themeBoot): void {
    if (strpos($themeBoot, 'ThemeManifest::isReservedPath') === false) {
        throw new RuntimeException('ThemeBoot must not intercept /system installer routes');
    }
};
$tests['clean install schema contains host theme bindings'] = static function () use ($dataSql): void {
    if (strpos($dataSql, 'CREATE TABLE IF NOT EXISTS `theme_bindings`') === false || strpos($dataSql, '`release_key` VARCHAR(128) NOT NULL') === false) {
        throw new RuntimeException('clean install schema missing theme_bindings');
    }
};

$failures = 0;
foreach ($tests as $name => $test) {
    try { $test(); fwrite(STDOUT, "PASS {$name}\n"); }
    catch (Throwable $e) { $failures++; fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n"); }
}
if ($failures) exit(1);
fwrite(STDOUT, count($tests) . " Theme config route tests passed.\n");
