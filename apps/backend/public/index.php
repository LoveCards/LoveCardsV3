<?php
// [ 应用入口文件 ]
namespace think;

$releaseRoot = dirname(__DIR__);
$bootstrap = $releaseRoot . '/bootstrap/ReleaseBootstrap.php';
if (!class_exists(\LoveCards\Bootstrap\ReleaseBootstrap::class, false)) {
    require $bootstrap;
}

try {
    $targetEntry = \LoveCards\Bootstrap\ReleaseBootstrap::webEntry($releaseRoot);
    if ($targetEntry !== null) {
        require $targetEntry;
        return;
    }
} catch (\Throwable $exception) {
    error_log('[LoveCards bootstrap] ' . $exception->getMessage());
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo '{"success":false,"error":{"code":9001,"message":"系统 Release 启动失败"}}';
    return;
}

$activeReleaseId = \LoveCards\Bootstrap\ReleaseBootstrap::activeReleaseId();
if ($activeReleaseId !== null) {
    header(\LoveCards\Bootstrap\ReleaseBootstrap::RELEASE_HEADER . ': ' . $activeReleaseId);
}
if (\LoveCards\Bootstrap\ReleaseBootstrap::isHealthRequest($_SERVER)) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['healthy' => true, 'release_id' => $activeReleaseId], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return;
}

require $releaseRoot . '/vendor/autoload.php';

// 宝塔 Nginx 兼容：panel 的 pathinfo.conf 会把 PATH_INFO 以空字符串传入 FPM，
// ThinkPHP 的 pathinfo() 优先读取已设置的 PATH_INFO，导致全部路由被解析为空路径。
// 这里在不改动 Nginx 配置的前提下，用 REQUEST_URI 重建真实请求路径。
if (isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] === '' && isset($_SERVER['REQUEST_URI'])) {
    $lcRequestPath = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $lcScriptName  = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    if ($lcRequestPath !== '' && $lcRequestPath !== $lcScriptName) {
        $_SERVER['PATH_INFO'] = $lcRequestPath;
    }
}

$app = new App($releaseRoot);
// 测试/脚本化运行的 env 隔离钩子（与 think 入口一致）：框架 Env 的 .env 文件
// 数据优先于进程环境变量，带 .env 的开发仓库里进程注入会被静默覆盖。
// 设置 LC_ENV_NAME 后 loadEnv 只读取 .env.<name>（通常不存在），进程环境生效。
$lcEnvName = getenv('LC_ENV_NAME');
if (is_string($lcEnvName) && trim($lcEnvName) !== '') {
    $app->setEnvName(trim($lcEnvName));
}
$runtimeRoot = \LoveCards\Bootstrap\ReleaseBootstrap::sharedRuntimeRoot();
if ($runtimeRoot !== null) {
    $app->setRuntimePath(rtrim($runtimeRoot, '/\\') . DIRECTORY_SEPARATOR);
}
$http = $app->http;

// 注册主题引擎全局中间件（必须在 run 之前）
$app->middleware->unshift(\app\frontend\middleware\ThemeBoot::class);

$response = $http->run();

if ($activeReleaseId !== null) {
    $response->header([\LoveCards\Bootstrap\ReleaseBootstrap::RELEASE_HEADER => $activeReleaseId]);
}

$response->send();

$http->end($response);
