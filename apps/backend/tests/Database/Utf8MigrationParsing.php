<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\modules\Operations\Installer\Infrastructure\Database;
use app\modules\Operations\SystemLifecycle\Infrastructure\SqlFileMigration;

function expectSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL {$message}\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$splitStatements = new ReflectionMethod(Database::class, 'splitStatements');
$splitStatements->setAccessible(true);
$sql = "INSERT INTO `capabilities` (`description`) VALUES ('查看系统运行和迁移状态');\n"
    . "SELECT '恢复系统 Release 指针';\n";
$statements = $splitStatements->invoke(null, $sql);
expectSame([
    "INSERT INTO `capabilities` (`description`) VALUES ('查看系统运行和迁移状态')",
    "SELECT '恢复系统 Release 指针'",
], $statements, 'SQL splitter preserves UTF-8 text');

$splitPhases = new ReflectionMethod(SqlFileMigration::class, 'splitPhases');
$splitPhases->setAccessible(true);
$migration = (new ReflectionClass(SqlFileMigration::class))->newInstanceWithoutConstructor();
$phases = $splitPhases->invoke(
    $migration,
    "-- @lc-phase expand\nSELECT '运行状态';\n-- @lc-phase verify\nSELECT '迁移完成';\n"
);
expectSame("SELECT '运行状态';", trim($phases['expand']), 'phase splitter preserves UTF-8 expand SQL');
expectSame("SELECT '迁移完成';", trim($phases['verify']), 'phase splitter preserves UTF-8 verify SQL');

echo "PASS UTF-8 migration parsing baseline\n";
