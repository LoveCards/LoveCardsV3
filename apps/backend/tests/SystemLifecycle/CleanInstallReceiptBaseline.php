<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Operations\SystemLifecycle\Infrastructure\MigrationCatalog;
use app\modules\Operations\SystemLifecycle\Public\CleanInstallReceipt;

$directory = sys_get_temp_dir() . '/lc-install-receipt-' . bin2hex(random_bytes(6));
if (!mkdir($directory)) throw new RuntimeException('fixture directory unavailable');
$migrations = $directory . '/migrations';
if (!mkdir($migrations)) throw new RuntimeException('fixture migration directory unavailable');
$schema = $directory . '/schema.sql';
$firstMigration = $migrations . '/20991231000001.sql';
$secondMigration = $migrations . '/20991231000002.sql';
try {
    file_put_contents($schema, "CREATE TABLE fixture (id INT);\n");
    file_put_contents($firstMigration, "CREATE TABLE fixture (id INT);\n");
    $receipt = new CleanInstallReceipt(new MigrationCatalog());
    $initial = $receipt->fingerprint($schema, $migrations);
    if (!preg_match('/^[a-f0-9]{64}$/', $initial)
        || $initial !== $receipt->fingerprint($schema, $migrations)) {
        throw new RuntimeException('clean install receipt must be stable SHA-256');
    }
    file_put_contents($secondMigration, "CREATE TABLE newer (id INT);\n");
    $withMigration = $receipt->fingerprint($schema, $migrations);
    if ($withMigration === $initial) throw new RuntimeException('new reviewed migration did not invalidate old receipt');
    file_put_contents($schema, "CREATE TABLE fixture (id BIGINT);\n");
    if ($receipt->fingerprint($schema, $migrations) === $withMigration) {
        throw new RuntimeException('changed target schema did not invalidate old receipt');
    }
    echo "PASS clean install receipt binds schema and migration catalog\n";
} finally {
    @unlink($schema);
    @unlink($firstMigration);
    @unlink($secondMigration);
    @rmdir($migrations);
    @rmdir($directory);
}
