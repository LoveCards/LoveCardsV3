<?php
declare(strict_types=1);

require dirname(__DIR__) . '/apps/backend/vendor/autoload.php';
require dirname(__DIR__) . '/apps/backend/vendor/topthink/framework/src/helper.php';

use app\modules\Operations\SystemLifecycle\Infrastructure\LocalBackupArtifactStore;
use app\shared\SystemPaths\SystemPaths;
use think\Container;
use think\DbManager;

if (getenv('LC_TEST_DB_ISOLATED') !== '1') {
    throw new RuntimeException('Refusing to run without LC_TEST_DB_ISOLATED=1');
}

$host = getenv('LC_TEST_DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('LC_TEST_DB_PORT') ?: 3306);
$user = getenv('LC_TEST_DB_USER') ?: 'root';
$password = getenv('LC_TEST_DB_PASSWORD') ?: '';
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$backendRoot = dirname(__DIR__) . '/apps/backend';
$app = new \think\App($backendRoot);
Container::setInstance($app);
$app->initialize();
$database = 'lovecards_e2e_backup_cold_' . bin2hex(random_bytes(5));
$instance = sys_get_temp_dir() . '/lc-backup-cold-' . bin2hex(random_bytes(5));
$admin->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4');
try {
    $db = new DbManager();
    $db->setConfig(['default' => 'mysql', 'connections' => ['mysql' => [
        'type' => 'mysql', 'hostname' => $host, 'hostport' => $port, 'database' => $database,
        'username' => $user, 'password' => $password, 'charset' => 'utf8mb4', 'prefix' => '',
    ]]]);
    Container::getInstance()->instance('think\DbManager', $db);
    $admin->exec('CREATE TABLE `' . $database . '`.`sample` (`id` INT PRIMARY KEY, `name` VARCHAR(32) NOT NULL)');
    $admin->exec("INSERT INTO `{$database}`.`sample` VALUES (1, 'backup-fixture')");
    $paths = new SystemPaths($instance, $backendRoot);
    $store = new LocalBackupArtifactStore($paths, static fn(): array => ['slug' => 'local', 'type' => 'local']);
    $artifact = $store->database('cold-connection');
    $store->verify($artifact);
    $contents = file_get_contents($artifact->location());
    if (!is_string($contents) || !str_contains($contents, 'backup-fixture')) {
        throw new RuntimeException('Cold connection backup did not capture table data');
    }
    echo "PASS isolated cold-connection database backup and verification\n";
} finally {
    if (!preg_match('/^lovecards_e2e_backup_cold_[a-f0-9]{10}$/D', $database)) throw new LogicException('Unsafe E2E database name');
    $admin->exec('DROP DATABASE `' . $database . '`');
    if (isset($artifact) && is_file($artifact->location())) unlink($artifact->location());
    foreach ([$instance . '/shared/backups', $instance . '/shared', $instance] as $directory) {
        if (is_dir($directory)) rmdir($directory);
    }
}
