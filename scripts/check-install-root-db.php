<?php
declare(strict_types=1);

require dirname(__DIR__) . '/apps/backend/vendor/autoload.php';

use app\modules\Platform\Auth\Public\InstallationAccount;
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
$database = 'lovecards_e2e_install_root_' . bin2hex(random_bytes(5));
$admin->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4');
try {
    $db = new DbManager();
    $db->setConfig(['default' => 'mysql', 'connections' => ['mysql' => [
        'type' => 'mysql', 'hostname' => $host, 'hostport' => $port, 'database' => $database,
        'username' => $user, 'password' => $password, 'charset' => 'utf8mb4', 'prefix' => '',
    ]]]);
    Container::getInstance()->instance('think\DbManager', $db);
    $db->connect()->execute('CREATE TABLE users (id BIGINT UNSIGNED PRIMARY KEY, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, status INT NOT NULL, security_version INT UNSIGNED NOT NULL, credentials_changed_at DATETIME NULL, deleted_at DATETIME NULL)');
    $db->connect()->execute('CREATE TABLE user_roles (user_id BIGINT UNSIGNED NOT NULL, role_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (user_id, role_id))');
    $db->connect()->execute("INSERT INTO users (id, email, password, status, security_version) VALUES (1, 'admin@lovecards.cn', '!', 0, 1)");
    $missingRoleRejected = false;
    try {
        InstallationAccount::setRootPassword('install-test-missing-role');
    } catch (RuntimeException) {
        $missingRoleRejected = true;
    }
    if (!$missingRoleRejected) throw new RuntimeException('Root bootstrap accepted a user without the root role');
    $db->connect()->execute('INSERT INTO user_roles (user_id, role_id) VALUES (1, 1)');
    $newPassword = 'install-test-' . bin2hex(random_bytes(12));
    InstallationAccount::setRootPassword($newPassword);
    $row = $db->connect()->query('SELECT password, security_version, credentials_changed_at FROM users WHERE id = 1')[0];
    if (!password_verify($newPassword, $row['password']) || (int) $row['security_version'] !== 2 || $row['credentials_changed_at'] === null) {
        throw new RuntimeException('Root credential was not persisted correctly');
    }
    echo "PASS isolated root installation credential persistence\n";
} finally {
    if (!preg_match('/^lovecards_e2e_install_root_[a-f0-9]{10}$/D', $database)) {
        throw new LogicException('Unsafe E2E database name');
    }
    $admin->exec('DROP DATABASE `' . $database . '`');
}
