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

$app = new App($releaseRoot);
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
