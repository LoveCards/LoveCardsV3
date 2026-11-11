<?php
declare(strict_types=1);

// 新安装的结构和默认数据必须有明确边界，避免重新引入运行时 schema diff。
$root = dirname(__DIR__, 2);
$schemaPath = $root . '/database/schema.sql';
$seedPath = $root . '/database/seed.sql';
$installPath = $root . '/app/system/controller/Install.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$schema = (string) @file_get_contents($schemaPath);
$seed = (string) @file_get_contents($seedPath);
$install = (string) @file_get_contents($installPath);
$assert($schema !== '', 'database/schema.sql must exist and be non-empty');
$assert($seed !== '', 'database/seed.sql must exist and be non-empty');
$assert((bool) preg_match('/\bCREATE\s+TABLE\b/i', $schema), 'schema.sql must contain table definitions');
$assert(!preg_match('/\bINSERT\s+INTO\b/i', $schema), 'schema.sql must not contain default data inserts');
$assert((bool) preg_match('/\bINSERT\s+INTO\b/i', $seed), 'seed.sql must contain default data inserts');
$assert(!preg_match('/\bCREATE\s+TABLE\b|\bALTER\s+TABLE\b|\bDROP\s+TABLE\b/i', $seed), 'seed.sql must not contain schema mutations');
foreach (['runtime_config_state', 'scheduler_runs', 'stream_tickets', 'notification_policies', 'notification_policy_versions'] as $table) {
    $assert(str_contains($schema, 'CREATE TABLE `' . $table . '`'), 'schema.sql must include ' . $table);
}
$assert(str_contains($schema, '`policy_revision` int(10) unsigned DEFAULT NULL'), 'event_deliveries must include policy_revision');
$assert(str_contains($schema, '`policy_decision` varchar(24) DEFAULT NULL'), 'event_deliveries must include policy_decision');
$assert(str_contains($seed, 'INSERT INTO `runtime_config_state`'), 'seed.sql must initialize runtime configuration');
$assert(str_contains($seed, 'INSERT INTO `notification_policy_versions`'), 'seed.sql must initialize notification policy version');
$assert(str_contains($seed, "'content.comment.published'"), 'seed.sql must use canonical notification event names');
foreach (['permissions', 'role_permissions', 'system'] as $legacyTable) {
    $assert(!str_contains($seed, 'INSERT INTO `' . $legacyTable . '`'), 'seed.sql must not repopulate legacy ' . $legacyTable);
}
$assert(str_contains($install, "databaseRoot . 'schema.sql'"), 'Installer must import schema.sql');
$assert(str_contains($install, "databaseRoot . 'seed.sql'"), 'Installer must import seed.sql');
$assert(!str_contains($install, "'data.sql'"), 'Installer must not import legacy data.sql');

if ($failures !== []) {
    fwrite(STDERR, "Schema/seed baseline failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Schema/seed baseline passed.\n");
