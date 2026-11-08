<?php
declare(strict_types=1);

// 隔离 E2E 的全新安装路径：导入目标结构后调用生产 SystemLifecycle 账本入口。
if (getenv('LOVECARDS_E2E_ISOLATED') !== '1'
    || !preg_match('/^lovecards_e2e_[a-z0-9_]+$/', (string) getenv('DATABASE_DATABASE'))
    || !in_array(getenv('DATABASE_HOSTNAME'), ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "Refusing non-isolated schema ledger operation\n");
    exit(1);
}

$backend = __DIR__ . '/../apps/backend';
require $backend . '/vendor/autoload.php';
$app = new \think\App($backend . DIRECTORY_SEPARATOR);
$app->initialize();
$app->make(\app\modules\Operations\SystemLifecycle\Application\SystemLifecycleCoordinator::class)
    ->recordInstalledSchema($backend . '/database/migrations');
\app\modules\Platform\Config\Public\Config::init();
\app\modules\Platform\Rbac\Service\Roles::seedSystemCapabilities();
echo "Installed schema ledger complete\n";
