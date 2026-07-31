<?php

$root = dirname(__DIR__, 2);
$route = file_get_contents($root . '/app/api/route/theme.php');
$sdk = file_get_contents(dirname($root, 2) . '/packages/sdk/src/resources/theme.ts');
$tests = [];

$tests['GET theme/config is registered exactly once'] = static function () use ($route): void {
    if (preg_match_all("/Route::get\\(['\"]theme\\/config['\"]/", $route) !== 1) {
        throw new RuntimeException('GET /theme/config 必须且只能注册一次');
    }
};
$tests['GET theme/config remains public'] = static function () use ($route): void {
    if (strpos($route, "->name('theme.publicConfig')") === false || strpos($route, "'public' => true") === false) {
        throw new RuntimeException('公开主题配置契约丢失');
    }
};
$tests['protected duplicate route name is removed'] = static function () use ($route): void {
    if (strpos($route, "->name('theme.config')") !== false) {
        throw new RuntimeException('仍存在重复的受保护 GET route');
    }
};
$tests['PUT theme/config remains protected'] = static function () use ($route): void {
    if (strpos($route, "Route::put('config', 'Theme.ThemeManager/updateConfig')") === false || strpos($route, "'caps' => ['theme.update']") === false) {
        throw new RuntimeException('PUT 主题配置契约被改变');
    }
};
$tests['SDK compatibility methods retain the same path'] = static function () use ($sdk): void {
    if (preg_match_all("/_get<ThemeConfigData>\\(['\"]\\/theme\\/config['\"]\\)/", $sdk) !== 2) {
        throw new RuntimeException('SDK config/publicConfig 路径发生变化');
    }
};

$failures = 0;
foreach ($tests as $name => $test) {
    try { $test(); fwrite(STDOUT, "PASS {$name}\n"); }
    catch (Throwable $e) { $failures++; fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n"); }
}
if ($failures) exit(1);
fwrite(STDOUT, count($tests) . " Theme config route tests passed.\n");
