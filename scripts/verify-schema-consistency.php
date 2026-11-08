<?php
declare(strict_types=1);

/**
 * Compare two disposable MySQL schemas without comparing auto-increment state.
 * Usage: php scripts/verify-schema-consistency.php DSN_A DSN_B
 * Credentials are read from LC_SCHEMA_COMPARE_USER/PASSWORD.
 */
$dsnA = $argv[1] ?? '';
$dsnB = $argv[2] ?? '';
if ($dsnA === '' || $dsnB === '') {
    fwrite(STDERR, "Usage: php scripts/verify-schema-consistency.php DSN_A DSN_B\n");
    exit(2);
}

$user = (string) (getenv('LC_SCHEMA_COMPARE_USER') ?: '');
$password = (string) (getenv('LC_SCHEMA_COMPARE_PASSWORD') ?: '');
$connect = static fn (string $dsn): PDO => new PDO(
    $dsn,
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$snapshot = static function (PDO $pdo): array {
    $tables = $pdo->query(
        'SELECT TABLE_NAME, ENGINE, ROW_FORMAT, TABLE_COLLATION, TABLE_COMMENT '
        . 'FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'
    )->fetchAll();
    $columns = $pdo->query(
        'SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_DEFAULT, IS_NULLABLE, DATA_TYPE, COLUMN_TYPE, EXTRA, COLLATION_NAME '
        . 'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION'
    )->fetchAll();
    $indexes = $pdo->query(
        'SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, INDEX_TYPE '
        . 'FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX'
    )->fetchAll();
    $foreignKeys = $pdo->query(
        'SELECT TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME '
        . 'FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL '
        . 'ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION'
    )->fetchAll();
    $foreignKeyRules = $pdo->query(
        'SELECT TABLE_NAME, CONSTRAINT_NAME, MATCH_OPTION, UPDATE_RULE, DELETE_RULE '
        . 'FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() '
        . 'ORDER BY TABLE_NAME, CONSTRAINT_NAME'
    )->fetchAll();
    return ['tables' => $tables, 'columns' => $columns, 'indexes' => $indexes, 'foreign_keys' => $foreignKeys, 'foreign_key_rules' => $foreignKeyRules];
};

$left = $snapshot($connect($dsnA));
$right = $snapshot($connect($dsnB));
if ($left !== $right) {
    foreach (['tables', 'columns', 'indexes', 'foreign_keys', 'foreign_key_rules'] as $section) {
        $count = max(count($left[$section]), count($right[$section]));
        for ($index = 0; $index < $count; $index++) {
            if (($left[$section][$index] ?? null) !== ($right[$section][$index] ?? null)) {
                fwrite(STDERR, "Schema mismatch in {$section} at row {$index}:\n");
                fwrite(STDERR, 'clean: ' . json_encode($left[$section][$index] ?? null, JSON_UNESCAPED_UNICODE) . "\n");
                fwrite(STDERR, 'migrated: ' . json_encode($right[$section][$index] ?? null, JSON_UNESCAPED_UNICODE) . "\n");
                break;
            }
        }
    }
    exit(1);
}
fwrite(STDOUT, "Schema consistency verified.\n");
