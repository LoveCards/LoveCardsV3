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
$assert(str_contains($install, "databaseRoot . 'schema.sql'"), 'Installer must import schema.sql');
$assert(str_contains($install, "databaseRoot . 'seed.sql'"), 'Installer must import seed.sql');
$assert(!str_contains($install, "'data.sql'"), 'Installer must not import legacy data.sql');

if ($failures !== []) {
    fwrite(STDERR, "Schema/seed baseline failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Schema/seed baseline passed.\n");
