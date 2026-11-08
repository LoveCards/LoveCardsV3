<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\modules\Operations\SystemLifecycle\Infrastructure\SqlFileMigration;
use app\shared\Infrastructure\Persistence\SqlStatementExecutor;

function expectSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL {$message}\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$sql = "INSERT INTO `capabilities` (`description`) VALUES ('查看系统运行和迁移状态');\n"
    . "SELECT '恢复系统 Release 指针';\n";
$statements = SqlStatementExecutor::splitStatements($sql);
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

$phases = $splitPhases->invoke($migration, "-- 历史迁移\n-- @lc-phase migrate\nSELECT 2;\n-- @lc-phase contract\nSELECT 1;\n");
expectSame('SELECT 2;', trim($phases['backfill']), 'historical migrate marker maps to backfill');
expectSame('', $phases['expand'], 'backfill must not execute during expand');
foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') as $path) {
    new SqlFileMigration(pathinfo($path, PATHINFO_FILENAME), $path, '解析回归');
}
echo "PASS UTF-8 migration parsing baseline\n";
