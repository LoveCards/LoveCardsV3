<?php

declare(strict_types=1);

/**
 * Export the API route manifest from ThinkPHP's runtime route registry.
 *
 * This is intentionally backend-only. The SDK is a generated consumer of
 * this manifest and must never be used as an input to produce it.
 */

$root = dirname(__DIR__);
$backend = $root . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . 'backend';

error_reporting(E_ERROR | E_PARSE);

require $backend . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$app = new \think\App($backend . DIRECTORY_SEPARATOR);
try {
    // The exporter is a read-only CLI tool. Do not install ThinkPHP's
    // process-wide error handler; it renders exceptions and exits before the
    // exporter can report a useful failure to its caller.
    $initializers = new \ReflectionProperty($app, 'initializers');
    $initializers->setAccessible(true);
    $initializers->setValue($app, [
        \think\initializer\RegisterService::class,
        \think\initializer\BootService::class,
    ]);
    $app->initialize();
    $app->route->clear();
    $app->route->lazy(false);
} catch (\Throwable $error) {
    fwrite(STDERR, $error->__toString() . PHP_EOL);
    exit(1);
}

$routeDir = $backend . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'route';
$files = glob($routeDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
sort($files);

foreach ($files as $file) {
    try {
        require $file;
    } catch (\Throwable $error) {
        fwrite(STDERR, $error->__toString() . PHP_EOL);
        exit(1);
    }
}

$routes = [];
foreach ($app->route->getRuleList() as $item) {
    $methodValue = $item['method'] ?? '';
    $methods = is_array($methodValue) ? $methodValue : [$methodValue];
    $methods = array_values(array_filter(array_map(static fn ($value): string => strtoupper((string) $value), $methods)));
    if ($methods === [] || in_array('*', $methods, true)) {
        continue;
    }

    $route = $item['route'] ?? null;
    $routeTarget = is_string($route) ? $route : null;
    [$controller, $action] = array_pad(explode('/', (string) $routeTarget, 2), 2, null);
    $option = is_array($item['option'] ?? null) ? $item['option'] : [];
    $meta = is_array($option['meta'] ?? null) ? $option['meta'] : [];
    $contract = is_array($option['contract'] ?? null) ? $option['contract'] : null;

    foreach ($methods as $method) {
    $routes[] = [
        'path' => normalizeRoutePath($item['rule'] ?? ''),
        'method' => $method,
        'name' => is_scalar($item['name'] ?? null) ? (string) $item['name'] : '',
        'controller' => $controller,
        'action' => $action,
        'meta' => [
            'name' => $meta['name'] ?? null,
            'group' => $meta['group'] ?? null,
            'access' => is_array($meta['access'] ?? null) ? $meta['access'] : null,
        ],
        'middleware' => normalizeMiddleware($item['option']['middleware'] ?? []),
        'contract' => $contract,
            'source' => str_replace('\\', '/', str_replace($root . DIRECTORY_SEPARATOR, '', $routeDir)),
        ];
    }
}

usort($routes, static function (array $left, array $right): int {
    return sprintf('%s %s %s', $left['method'], $left['path'], $left['name'])
        <=> sprintf('%s %s %s', $right['method'], $right['path'], $right['name']);
});

echo json_encode([
    'source' => 'thinkphp-runtime-route-registry',
    'routeCount' => count($routes),
    'routes' => $routes,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

function normalizeRoutePath($path): string
{
    if (is_array($path)) {
        $path = $path[0] ?? '';
    }
    $path = (string) $path;
    $path = preg_replace('/<([A-Za-z_][A-Za-z0-9_]*)>/', ':$1', $path) ?: $path;
    $path = '/' . trim(str_replace('\\', '/', $path), '/');
    $path = preg_replace('#/+#', '/', $path) ?: '/';

    return $path === '/' ? '/' : rtrim($path, '/');
}

function normalizeMiddleware($middleware): array
{
    if (!is_array($middleware)) {
        return is_string($middleware) && $middleware !== '' ? [$middleware] : [];
    }

    $result = [];
    foreach ($middleware as $entry) {
        if (is_string($entry) && $entry !== '') {
            $result[] = $entry;
            continue;
        }
        if (is_array($entry) && isset($entry[0]) && is_string($entry[0])) {
            $result[] = $entry[0];
            continue;
        }
        $result = array_merge($result, normalizeMiddleware($entry));
    }

    return array_values(array_unique($result));
}
