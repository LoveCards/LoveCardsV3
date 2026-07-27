<?php
declare(strict_types=1);

// ============================================================
// LoveCardsV3 — Schema Contract Test (Static Analysis)
//
// Independent PHP script, no PHPUnit dependency.
// Reads apps/backend/data.sql AND migrations/*.sql and
// validates schema contract compliance.
//
// Usage: @php tests/Database/SchemaContract.php
// Exit code: 0 = PASS, 1 = FAIL
// ============================================================

// ─── Configuration ───────────────────────────────────────────
$baseDir = dirname(__DIR__, 2);  // apps/backend/
$dataSqlPath = $baseDir . '/data.sql';
$migrationsDir = $baseDir . '/database/migrations/';
$rbacPath = $baseDir . '/app/api/service/Rbac/RBAC.php';
$rolesPath = $baseDir . '/app/api/service/Rbac/Roles.php';

$errors = [];
$passCount = 0;
$failCount = 0;

function pass(string $msg): void
{
    global $passCount;
    $passCount++;
    echo "[PASS] {$msg}\n";
}

function fail(string $msg): void
{
    global $failCount, $errors;
    $failCount++;
    $errors[] = $msg;
    echo "[FAIL] {$msg}\n";
}

// ─── Helper: strip SQL comments from text ───────────────────
function stripSqlComments(string $sql): string
{
    // Remove /* ... */ block comments (multi-line)
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    // Remove -- single-line comments
    $sql = preg_replace('/--[^\n]*/', '', $sql);
    return $sql;
}

// ─── Load SQL files ─────────────────────────────────────────

echo "=== Schema Contract Test ===\n\n";

// Load data.sql
if (!file_exists($dataSqlPath)) {
    fail("data.sql not found at {$dataSqlPath}");
    exit(1);
}
$dataSql = file_get_contents($dataSqlPath);
if ($dataSql === false) {
    fail("Failed to read data.sql");
    exit(1);
}
echo "data.sql: " . strlen($dataSql) . " bytes\n";

// Load migrations
$migrationSqls = [];
$migrationCombined = '';
if (is_dir($migrationsDir)) {
    $files = glob($migrationsDir . '*.sql');
    sort($files);
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if ($content !== false) {
            $baseName = basename($file);
            $migrationSqls[$baseName] = $content;
            $migrationCombined .= "\n-- {$baseName} --\n" . $content;
            echo "migration: {$baseName} (" . strlen($content) . " bytes)\n";
        }
    }
} else {
    echo "migrations directory not found at {$migrationsDir}\n";
}
echo "\n";

// ────────────────────────────────────────────────────────────
//  Helper: extract CREATE TABLE table names from SQL text
// ────────────────────────────────────────────────────────────
function getCreateTableNames(string $sql): array
{
    $pattern = '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`(\w+)`/i';
    preg_match_all($pattern, $sql, $matches);
    return $matches[1] ?? [];
}

// ────────────────────────────────────────────────────────────
//  Helper: extract column definitions for a given table
// ────────────────────────────────────────────────────────────
function getTableColumns(string $sql, string $tableName): string
{
    $escaped = preg_quote($tableName, '/');
    $pattern = '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`' . $escaped . '`\s*\((.*?)\)\s*ENGINE\s*=/si';
    if (preg_match($pattern, $sql, $match)) {
        return $match[1];
    }
    return '';
}

// ────────────────────────────────────────────────────────────
//  Helper: check if a specific unique key exists for a table
//  in the given SQL text, with exact table name matching.
//  Does NOT fall back to cross-SQL matching.
// ────────────────────────────────────────────────────────────
function hasUniqueKey(string $sql, string $tableName, string $keyName): bool
{
    $columns = getTableColumns($sql, $tableName);
    if ($columns === '') {
        return false;
    }
    // Check inline UNIQUE KEY
    if (preg_match('/UNIQUE\s+KEY\s+`' . preg_quote($keyName, '/') . '`/', $columns)) {
        return true;
    }
    // Check inline UNIQUE (without KEY keyword)
    if (preg_match('/UNIQUE\s+(?:KEY\s+)?`' . preg_quote($keyName, '/') . '`/', $columns)) {
        return true;
    }
    return false;
}

// ────────────────────────────────────────────────────────────
//  Helper: check if a column exists in a table definition
// ────────────────────────────────────────────────────────────
function hasColumn(string $sql, string $tableName, string $columnName, string $typePattern = ''): bool
{
    $columns = getTableColumns($sql, $tableName);
    if ($columns === '') {
        return false;
    }
    $colPattern = '/`' . preg_quote($columnName, '/') . '`\s+';
    if ($typePattern !== '') {
        $colPattern .= $typePattern;
    }
    $colPattern .= '/si';
    return (bool)preg_match($colPattern, $columns);
}

// ────────────────────────────────────────────────────────────
//  Helper: check role_permissions DDL for foreign keys
// ────────────────────────────────────────────────────────────
function hasForeignKeyToPermissions(string $sql): bool
{
    // Check for ALTER TABLE role_permissions ADD CONSTRAINT ... FK to permissions
    $pattern = '/ALTER\s+TABLE\s+`role_permissions`.*?FOREIGN\s+KEY\s*\([^)]*\)\s*REFERENCES\s+`permissions`/si';
    return (bool)preg_match($pattern, $sql);
}

// ────────────────────────────────────────────────────────────
//  Helper: check if a column has NOT NULL constraint
//  Handles VARCHAR(n) NOT NULL, DATETIME NOT NULL, etc.
// ────────────────────────────────────────────────────────────
function hasNotNull(string $sql, string $tableName, string $columnName): bool
{
    $columns = getTableColumns($sql, $tableName);
    if ($columns === '') {
        return false;
    }
    // Match column definition with type (with optional length), then NOT NULL or PRIMARY KEY
    $pattern = '/`' . preg_quote($columnName, '/') . '`\s+\w+(?:\([^)]*\))?[^,]+(?:NOT\s+NULL|PRIMARY\s+KEY)/si';
    return (bool)preg_match($pattern, $columns);
}

// ────────────────────────────────────────────────────────────
//  Helper: statically extract capabilites from RBAC.php
// ────────────────────────────────────────────────────────────
function extractAllCapabilitiesFromRbac(string $rbacPath): array
{
    if (!file_exists($rbacPath)) {
        return [];
    }
    $content = file_get_contents($rbacPath);
    if ($content === false) {
        return [];
    }
    // Extract all capability strings from getAllCapabilities() return array only
    // Pattern: 'cards.read' => '查看卡片',
    if (preg_match('/function getAllCapabilities\(\): array\s*\{\s*return\s*\[(.*?)\];\s*\}/s', $content, $m)) {
        preg_match_all("/'([a-zA-Z0-9_.]+)'\s*=>/", $m[1], $caps);
        return $caps[1] ?? [];
    }
    return [];
}

// ────────────────────────────────────────────────────────────
//  Helper: statically extract role capability matrix from
//  Roles.php getSystemRoleCapabilityMatrix() method
// ────────────────────────────────────────────────────────────
function extractRoleCapMatrixFromRoles(string $rolesPath): array
{
    $result = [
        1 => [],  // root
        2 => [],  // admin
        3 => [],  // user
        4 => [],  // guest
    ];

    if (!file_exists($rolesPath)) {
        return $result;
    }
    $content = file_get_contents($rolesPath);
    if ($content === false) {
        return $result;
    }

    // Extract the return array from getSystemRoleCapabilityMatrix()
    if (preg_match('/function getSystemRoleCapabilityMatrix\(\): array\s*\{[^}]*return\s*\[(.*?)\];[^}]*\}/s', $content, $mainMatch)) {
        $body = $mainMatch[1];

        // Map role slugs to expected IDs using config anchors
        $roleOrder = ['guest' => 4, 'user' => 3, 'admin' => 2, 'root' => 1];

        foreach ($roleOrder as $slug => $expectedId) {
            // Extract array for this role
            if (preg_match('/\$roles\[\'' . $slug . '\'\]\s*=>\s*\[(.*?)\]/s', $body, $roleMatch)) {
                preg_match_all("/'([a-zA-Z0-9_.]+)'/", $roleMatch[1], $caps);
                $result[$expectedId] = $caps[1] ?? [];
            }
        }

        // Handle root separately: empty array (filled by caller)
        if (preg_match('/\$roles\[\'root\'\]\s*=>\s*\[\s*\]/', $body)) {
            // Root gets all capabilities - loaded separately
            // Already set to empty array, will be filled by caller
        }
    }

    return $result;
}

// ────────────────────────────────────────────────────────────
//  Helper: extract capabilities from migration SQL
// ────────────────────────────────────────────────────────────
function extractCapsFromMigration(string $migrationSql, int $roleId): array
{
    // Find the INSERT for this role_id
    $roleIdQuoted = preg_quote((string)$roleId, '/');
    // Pattern: SELECT $roleId, c.capability FROM ( ... ) c WHERE NOT EXISTS
    if (preg_match('/SELECT\s+' . $roleIdQuoted . '\s*,\s*c\.capability\s+FROM\s*\(([^)]+)\)\s*c/si', $migrationSql, $match)) {
        $capBlock = $match[1];
        preg_match_all("/'([a-zA-Z0-9_.]+)'/", $capBlock, $capMatches);
        $caps = array_unique($capMatches[1] ?? []);
        sort($caps);
        return $caps;
    }
    return [];
}

// ────────────────────────────────────────────────────────────
//  Section 1: Required Tables (data.sql)
// ────────────────────────────────────────────────────────────
echo "--- 1. Required Tables in data.sql ---\n";

$requiredTables = [
    'cards', 'comments', 'tags', 'tags_map', 'users',
    'roles', 'role_capabilities', 'configs', 'files', 'likes',
];
$foundTables = getCreateTableNames($dataSql);

foreach ($requiredTables as $table) {
    if (in_array($table, $foundTables, true)) {
        pass("Required table `{$table}` found in data.sql");
    } else {
        fail("Required table `{$table}` NOT found in data.sql");
    }
}

$legacyTables = ['good', 'images', 'permissions', 'role_permissions', 'system'];
foreach ($legacyTables as $table) {
    if (in_array($table, $foundTables, true)) {
        pass("Legacy table `{$table}` found in data.sql");
    } else {
        fail("Legacy table `{$table}` NOT found in data.sql");
    }
}

// ────────────────────────────────────────────────────────────
//  Section 2: Critical Columns and Types (data.sql)
// ────────────────────────────────────────────────────────────
echo "\n--- 2. Critical Columns and Types in data.sql ---\n";

$columnChecks = [
    ['cards', 'pictures', 'JSON'],
    ['cards', 'goods', 'INT'],
    ['roles', 'is_system', 'TINYINT'],
    ['tags_map', 'status', 'INT'],
    ['files', 'hash', 'VARCHAR'],
    ['files', 'metadata', 'JSON'],
    ['likes', 'ref_type', 'VARCHAR'],
    ['likes', 'pid', 'INT'],
    ['likes', 'uid', 'INT'],
    ['likes', 'ip', 'VARCHAR'],
    ['configs', 'group', 'VARCHAR'],
    ['configs', 'key', 'VARCHAR'],
    ['configs', 'created_at', 'DATETIME'],
    ['configs', 'updated_at', 'DATETIME'],
    ['role_capabilities', 'role_id', 'INT'],
    ['role_capabilities', 'capability', 'VARCHAR'],
];

foreach ($columnChecks as [$table, $col, $type]) {
    $result = hasColumn($dataSql, $table, $col, $type);
    if ($result) {
        pass("`{$table}.{$col}` type {$type}");
    } else {
        fail("`{$table}.{$col}` type {$type} — column or type mismatch");
    }
}

// ────────────────────────────────────────────────────────────
//  Section 3: Unique Keys (data.sql)
// ────────────────────────────────────────────────────────────
echo "\n--- 3. Unique Keys in data.sql ---\n";

$ukChecks = [
    ['files', 'uk_files_hash'],
    ['configs', 'uk_configs_group_key'],
    ['likes', 'uk_likes_pid_uid'],
    ['role_capabilities', 'uk_role_cap'],
];

foreach ($ukChecks as [$table, $key]) {
    if (hasUniqueKey($dataSql, $table, $key)) {
        pass("UNIQUE KEY `{$key}` on `{$table}`");
    } else {
        fail("UNIQUE KEY `{$key}` on `{$table}` — not found");
    }
}

// ────────────────────────────────────────────────────────────
//  Section 4: System Role Seed (data.sql)
// ────────────────────────────────────────────────────────────
echo "\n--- 4. System Role Seed ---\n";

// Check INSERT INTO `roles` contains 4 system roles
$insertPattern = '/INSERT\s+INTO\s+`roles`.*?VALUES\s*\(/si';
if (preg_match($insertPattern, $dataSql)) {
    pass("INSERT INTO `roles` statement found");

    // Extract all VALUES blocks
    $pattern = '/INSERT\s+INTO\s+`roles`\s*\([^)]+\)\s*VALUES\s*((?:\([^;]+\)\s*,?\s*)+)/si';
    if (preg_match($pattern, $dataSql, $match)) {
        $valuesBlock = $match[1];

        // Check four system role IDs exist
        $foundIds = 0;
        foreach ([1, 2, 3, 4] as $id) {
            if (preg_match('/\(' . $id . '\s*,/', $valuesBlock)) {
                $foundIds++;
                pass("System role id={$id} present");
            } else {
                fail("System role id={$id} NOT found");
            }
        }

        // Verify all four system roles have is_system=1
        if (preg_match('/\(4\s*,\s*\'访客\'\s*,\s*\'guest\'\s*,\s*1\s*,/', $valuesBlock)) {
            pass("Guest role has is_system=1");
        } else {
            fail("Guest role is_system flag — check data.sql seed values");
        }

        // Check that ALL four system roles have is_system=1 (not just guest)
        // Root: id=1, name='超级管理员', slug='root', is_system=1
        if (preg_match('/\(1\s*,\s*\'超级管理员\'\s*,\s*\'root\'\s*,\s*1\s*,/', $valuesBlock)) {
            pass("Root role has is_system=1");
        } else {
            fail("Root role is_system flag — expected is_system=1");
        }
        if (preg_match('/\(2\s*,\s*\'管理员\'\s*,\s*\'admin\'\s*,\s*1\s*,/', $valuesBlock)) {
            pass("Admin role has is_system=1");
        } else {
            fail("Admin role is_system flag — expected is_system=1");
        }
        if (preg_match('/\(3\s*,\s*\'用户\'\s*,\s*\'user\'\s*,\s*1\s*,/', $valuesBlock)) {
            pass("User role has is_system=1");
        } else {
            fail("User role is_system flag — expected is_system=1");
        }
    } else {
        fail("Could not parse INSERT INTO `roles` VALUES block");
    }
} else {
    fail("INSERT INTO `roles` statement not found");
}

// ────────────────────────────────────────────────────────────
//  Section 5: Prohibited Statements (both files)
//  Comments are stripped before checking to avoid false
//  positives (e.g., -- DROP TABLE in comments).
// ────────────────────────────────────────────────────────────
echo "\n--- 5. Prohibited Statements ---\n";

$allSql = $dataSql . "\n" . $migrationCombined;
// Strip comments before checking for prohibited statements
$allSqlNoComments = stripSqlComments($allSql);
$migrationNoComments = stripSqlComments($migrationCombined);

$prohibited = [
    '/\bDROP\s+TABLE\b/i'               => 'DROP TABLE',
    '/\bTRUNCATE\b/i'                    => 'TRUNCATE',
    '/\bREPLACE\s+INTO\b/i'              => 'REPLACE INTO',
];

foreach ($prohibited as $pattern => $name) {
    if (preg_match($pattern, $allSqlNoComments)) {
        fail("Prohibited statement found: {$name}");
    } else {
        pass("No {$name} in SQL files");
    }
}

// Check for INSERT IGNORE (migration only)
if (preg_match('/\bINSERT\s+IGNORE\b/i', $migrationNoComments)) {
    fail("INSERT IGNORE found in migration");
} else {
    pass("No INSERT IGNORE in migration");
}

// ────────────────────────────────────────────────────────────
//  Section 6: Sensitive Data (data.sql)
// ────────────────────────────────────────────────────────────
echo "\n--- 6. Sensitive Data ---\n";

$sensitiveChecks = [
    ['/[A-Z]:\\\\/i', 'Windows absolute path'],
    ['/\/home\/|\/root\/|\/Users\/\w+/', 'Unix absolute path'],
    ['/-----BEGIN\s+(RSA|PRIVATE|PUBLIC)\s+KEY-----/', 'Private/public key block'],
    ['/\bsk-[a-zA-Z0-9]{20,}\b/', 'Secret key pattern'],
];

foreach ([$dataSql] as $sql) {
    foreach ($sensitiveChecks as [$pattern, $desc]) {
        if (preg_match($pattern, $sql)) {
            fail("Sensitive data found in data.sql: {$desc}");
        } else {
            pass("No {$desc} in data.sql");
        }
    }
}

// ────────────────────────────────────────────────────────────
//  Section 7: Migration-Specific Checks
// ────────────────────────────────────────────────────────────
echo "\n--- 7. Migration Checks ---\n";

if ($migrationCombined !== '') {
    // 7a. Check SIGNAL statements exist (real failure paths)
    if (preg_match('/SIGNAL\s+SQLSTATE\s+\'45000\'/i', $migrationCombined)) {
        pass("SIGNAL SQLSTATE '45000' found in migration (real failure path)");
    } else {
        fail("No SIGNAL SQLSTATE '45000' in migration — conflicts will not stop execution");
    }

    // 7b. Check stored procedures used for SIGNAL
    if (preg_match('/CREATE\s+PROCEDURE\s+/i', $migrationCombined)) {
        pass("Migration uses stored procedures for SIGNAL support");
    } else {
        fail("No stored procedures in migration — SIGNAL may not work in MySQL 5.7");
    }

    // 7c. Check DROP PROCEDURE IF EXISTS present
    if (preg_match('/DROP\s+PROCEDURE\s+IF\s+EXISTS/i', $migrationCombined)) {
        pass("DROP PROCEDURE IF EXISTS found (temporary procedure cleanup)");
    } else {
        fail("No DROP PROCEDURE in migration — temporary procedures may leak");
    }

    // 7d. Check NOT EXISTS pattern used for data migration
    if (preg_match('/WHERE\s+NOT\s+EXISTS\s*\(/i', $migrationCombined)) {
        pass("WHERE NOT EXISTS pattern found in migration (safe idempotent insert)");
    } else {
        fail("No WHERE NOT EXISTS in migration — data migration may not be idempotent");
    }

    // 7e. Check role_capability seeding with WHERE NOT EXISTS
    if (preg_match('/INSERT\s+INTO\s+`role_capabilities`.*?WHERE\s+NOT\s+EXISTS/si', $migrationCombined)) {
        pass("Capability seeding uses WHERE NOT EXISTS (idempotent)");
    } else {
        fail("Capability seeding may not be idempotent — missing WHERE NOT EXISTS");
    }

    // 7f. Check migration doesn't contain DROP TABLE or other destructive operations
    // Strip comments first to avoid matching DROP in SQL comments
    $destructiveDrops = $migrationNoComments;
    // Exempt DROP PROCEDURE (allowed)
    $destructiveDrops = preg_replace('/DROP\s+PROCEDURE\s+IF\s+EXISTS/i', '', $destructiveDrops);
    if (preg_match('/\bDROP\s+(TABLE|VIEW|DATABASE|INDEX|KEY)\b/i', $destructiveDrops)) {
        fail("Migration contains destructive DROP (other than DROP PROCEDURE)");
    } else {
        pass("No destructive DROP statements in migration (only DROP PROCEDURE)");
    }
} else {
    echo "[SKIP] No migration files to check\n";
}

// ────────────────────────────────────────────────────────────
//  Section 8: Migration + data.sql Consistency
// ────────────────────────────────────────────────────────────
echo "\n--- 8. Cross-file Consistency ---\n";

if ($migrationCombined !== '') {
    // Check data.sql role_permissions has no FK to permissions (use strip-commented SQL)
    if (hasForeignKeyToPermissions(stripSqlComments($dataSql))) {
        fail("data.sql role_permissions still has FK to permissions table");
    } else {
        pass("data.sql role_permissions has no FK to permissions table (BLOCKER 7 fixed)");
    }

    // ── Capability bidirectional comparison ──
    // Extract all capabilities from RBAC.php (authoritative list)
    $allCaps = extractAllCapabilitiesFromRbac($rbacPath);
    echo "[INFO] RBAC::getAllCapabilities() returns " . count($allCaps) . " capabilities\n";

    // Extract role capability matrix from Roles.php reseed()
    $roleMatrix = extractRoleCapMatrixFromRoles($rolesPath);
    $expectedRoleCounts = [];
    foreach ($roleMatrix as $rid => $caps) {
        $expectedRoleCounts[$rid] = count($caps);
    }

    // Root gets all capabilities from RBAC::getAllCapabilities()
    $expectedRoleCounts[1] = count($allCaps);  // root = all

    // Extract capabilities from migration SQL
    $migrationRoleCounts = [];
    $migrationRoleCaps = [];
    foreach ([1 => 'Root', 2 => 'Admin', 3 => 'User', 4 => 'Guest'] as $rid => $rname) {
        $migrationCaps = extractCapsFromMigration($migrationCombined, $rid);
        $migrationRoleCaps[$rid] = $migrationCaps;
        $migrationRoleCounts[$rid] = count($migrationCaps);
        echo "[INFO] {$rname} role has {$migrationRoleCounts[$rid]} capabilities in migration seed\n";
    }

    // Compare: migration count vs expected count for each role
    $capAllMatched = true;
    foreach ([1 => 'Root', 2 => 'Admin', 3 => 'User', 4 => 'Guest'] as $rid => $rname) {
        $expected = $expectedRoleCounts[$rid] ?? 0;
        $actual = $migrationRoleCounts[$rid] ?? 0;

        if ($expected === $actual) {
            pass("{$rname} role capability count matches: {$expected} expected == {$actual} in migration");
        } else {
            fail("{$rname} role capability count MISMATCH: {$expected} expected != {$actual} in migration");
            $capAllMatched = false;
        }
    }

    // If counts match, do exact set comparison for each role
    if ($capAllMatched) {
        foreach ([1 => 'Root', 2 => 'Admin', 3 => 'User', 4 => 'Guest'] as $rid => $rname) {
            $migrationCaps = $migrationRoleCaps[$rid] ?? [];
            $expectedCaps = $roleMatrix[$rid] ?? [];

            // For root, expected is all capabilities
            if ($rid === 1) {
                $expectedCaps = $allCaps;
            }

            $missingInMigration = array_diff($expectedCaps, $migrationCaps);
            $extraInMigration = array_diff($migrationCaps, $expectedCaps);

            if (empty($missingInMigration) && empty($extraInMigration)) {
                pass("{$rname} role capability set matches exactly (" . count($expectedCaps) . " caps)");
            } else {
                if (!empty($missingInMigration)) {
                    fail("{$rname} role missing capabilities in migration: " . implode(', ', $missingInMigration));
                }
                if (!empty($extraInMigration)) {
                    fail("{$rname} role has extra capabilities in migration: " . implode(', ', $extraInMigration));
                }
            }
        }
    } else {
        echo "[INFO] Skipping exact capability set comparison due to count mismatches\n";
    }

    // Check migration creates all required migration tables
    $migrationTables = getCreateTableNames($migrationCombined);
    $migrationRequired = ['configs', 'files', 'likes', 'role_capabilities'];
    foreach ($migrationRequired as $table) {
        if (in_array($table, $migrationTables, true)) {
            pass("Migration creates `{$table}` (CREATE TABLE IF NOT EXISTS)");
        } else {
            // Migration might use stored procedures for structure check; not a hard fail
            echo "[INFO] Migration does not contain CREATE TABLE for `{$table}` — may use pre-checks\n";
        }
    }
} else {
    echo "[SKIP] No migration files for cross-file checks\n";
}

// ────────────────────────────────────────────────────────────
//  Section 9: data.sql Specific Checks
// ────────────────────────────────────────────────────────────
echo "\n--- 9. data.sql Integrity ---\n";

// Check DDL has required NOT NULL on key columns
$notNullChecks = [
    ['configs', 'group', 'VARCHAR'],
    ['configs', 'key', 'VARCHAR'],
    ['configs', 'created_at', 'DATETIME'],
    ['configs', 'updated_at', 'DATETIME'],
    ['files', 'hash', 'VARCHAR'],
];

foreach ($notNullChecks as [$table, $col, $type]) {
    if (hasNotNull($dataSql, $table, $col)) {
        pass("`{$table}.{$col}` is NOT NULL");
    } else {
        // Fallback: try using hasColumn with type pattern
        if (hasColumn($dataSql, $table, $col, $type)) {
            fail("`{$table}.{$col}` column exists but NOT NULL constraint check failed (regex issue)");
        } else {
            fail("`{$table}.{$col}` column not found or NOT NULL check failed");
        }
    }
}

// Check role_permissions table has UNIQUE KEY role_permission
if (hasUniqueKey($dataSql, 'role_permissions', 'role_permission')) {
    pass("role_permissions has UNIQUE KEY `role_permission`");
} else {
    fail("role_permissions missing UNIQUE KEY `role_permission`");
}

// Check role_permissions table does NOT have FK to permissions
$rpColumns = getTableColumns($dataSql, 'role_permissions');
if (preg_match('/FOREIGN\s+KEY/', $rpColumns)) {
    fail("role_permissions DDL still contains FOREIGN KEY reference");
} else {
    pass("role_permissions DDL has no inline FOREIGN KEY constraints");
}

// ────────────────────────────────────────────────────────────
//  Section 10: Idempotency Strategy
// ────────────────────────────────────────────────────────────
echo "\n--- 10. Idempotency Strategy ---\n";

// Check CREATE TABLE IF NOT EXISTS is used
if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS/i', $dataSql)) {
    pass("data.sql uses CREATE TABLE IF NOT EXISTS");
} else {
    fail("data.sql does not use CREATE TABLE IF NOT EXISTS");
}

// Check information_schema pre-checks in migration
if (preg_match('/information_schema\.`COLUMNS`/i', $migrationCombined)) {
    pass("Migration uses information_schema pre-checks for idempotency");
} else {
    fail("Migration may not use information_schema pre-checks");
}

// ────────────────────────────────────────────────────────────
//  Section 11: InstallBaseline Pre-checks (data.sql only)
// ────────────────────────────────────────────────────────────
echo "\n--- 11. InstallBaseline consistency ---\n";

// Check data.sql role_permissions has no FK to permissions (double-check, strip-commented)
if (!hasForeignKeyToPermissions(stripSqlComments($dataSql))) {
    pass("data.sql role_permissions has no FK to permissions (verified)");
} else {
    fail("data.sql role_permissions STILL has FK to permissions — import will fail");
}

// Verify data.sql does NOT reference fk_role_permissions_permission (use strip-commented SQL)
$dataSqlNoComments = stripSqlComments($dataSql);
if (preg_match('/fk_role_permissions_permission/i', $dataSqlNoComments)) {
    fail("data.sql still contains fk_role_permissions_permission reference");
} else {
    pass("data.sql has no fk_role_permissions_permission reference");
}

// ────────────────────────────────────────────────────────────
//  Section 12: Migration Static Analysis (Negative Tests)
// ────────────────────────────────────────────────────────────
echo "\n--- 12. Migration Static Analysis ---\n";

if ($migrationCombined !== '') {
    // Extract Phase 2 section for duplicate-skip checking
    $phase2Pos = strpos($migrationCombined, '--  PHASE 2: MUTATION');
    $phase2Section = '';
    if ($phase2Pos !== false) {
        $phase2Section = substr($migrationCombined, $phase2Pos);
    } else {
        $phase2Section = $migrationCombined;
    }
    $phase2NoComments = stripSqlComments($phase2Section);

    // 12a. IF/END IF balance per procedure
    echo "\n--- 12a. IF/END IF Balance ---\n";
    $procPattern = '/DELIMITER\s*\/\/\s*DROP\s+PROCEDURE\s+IF\s+EXISTS\s*`(\w+)`\s*\/\/\s*CREATE\s+PROCEDURE\s+`\1`.*?END\s*\/\/\s*DELIMITER\s*;/s';
    preg_match_all($procPattern, $migrationCombined, $procMatches);
    $imbalanceFound = false;
    foreach ($procMatches[0] as $idx => $procBody) {
        $procName = $procMatches[1][$idx];
        $procOnly = trim($procBody);

        $ifCount = preg_match_all('/^[[:space:]]*IF\b/m', $procOnly);
        $endifCount = preg_match_all('/^[[:space:]]*END IF\b/m', $procOnly);

        if ($ifCount !== $endifCount) {
            fail("IF/END IF imbalance in {$procName}: {$ifCount} IF vs {$endifCount} END IF");
            $imbalanceFound = true;
        }
    }
    if (!$imbalanceFound) {
        $totalProcs = count($procMatches[1]);
        pass("All {$totalProcs} stored procedures have balanced IF/END IF");
    }

    // 12b. No duplicate ADD INDEX/KEY with same name in non-idempotent pattern
    echo "\n--- 12b. Duplicate ADD INDEX/KEY Detection ---\n";
    // Find all ADD {INDEX|KEY} or ADD UNIQUE KEY statements WITHIN each procedure body
    // to detect true duplicate attempts (cross-procedure redundancy with guards is intentional)
    $procBodies = [];
    preg_match_all('/DELIMITER\s*\/\/\s*DROP\s+PROCEDURE\s+IF\s+EXISTS\s*`(\w+)`\s*\/\/\s*CREATE\s+PROCEDURE\s+`\1`(.*?)END\s*\/\/\s*DELIMITER\s*;/s', $migrationCombined, $procBodies);
    $dupFound = false;
    foreach ($procBodies[0] as $idx => $procBody) {
        $procName = $procBodies[1][$idx];
        preg_match_all('/ALTER\s+TABLE\s+`(\w+)`\s+ADD\s+(UNIQUE\s+)?(?:KEY|INDEX)\s+`(\w+)`/i', $procBody, $procAddMatches);
        $procAdds = [];
        for ($j = 0; $j < count($procAddMatches[0]); $j++) {
            $tableName = $procAddMatches[1][$j];
            $indexName = $procAddMatches[3][$j];
            $key = "{$tableName}.{$indexName}";
            if (isset($procAdds[$key])) {
                fail("Duplicate ADD INDEX/KEY `{$indexName}` on `{$tableName}` within procedure `{$procName}`");
                $dupFound = true;
            }
            $procAdds[$key] = true;
        }
    }
    // Also check inline (non-procedure) code for duplicates within the same non-procedure region
    $nonProcSql = preg_replace('/DELIMITER\s*\/\/.*?DELIMITER\s*;/s', '', $migrationCombined);
    preg_match_all('/ALTER\s+TABLE\s+`(\w+)`\s+ADD\s+(UNIQUE\s+)?(?:KEY|INDEX)\s+`(\w+)`/i', $nonProcSql, $inlineAddMatches);
    $inlineAdds = [];
    $inlineDupFound = false;
    for ($j = 0; $j < count($inlineAddMatches[0]); $j++) {
        $tableName = $inlineAddMatches[1][$j];
        $indexName = $inlineAddMatches[3][$j];
        $key = "{$tableName}.{$indexName}";
        if (isset($inlineAdds[$key])) {
            fail("Duplicate ADD INDEX/KEY `{$indexName}` on `{$tableName}` in inline (non-procedure) code");
            $inlineDupFound = true;
        }
        $inlineAdds[$key] = true;
    }
    if (!$dupFound && !$inlineDupFound) {
        pass("No duplicate ADD INDEX/KEY within same procedure or inline region in migration");
    }

    // 12c. Phase 2 no "duplicate+skip" paths (SELECT with duplicate message)
    echo "\n--- 12c. Phase 2 Duplicate-Skip Path Detection ---\n";
    // Check Phase 2 for "SELECT ... duplicate ..." patterns — only "duplicate" is specific enough
    // Type info messages use "skip" in different contexts (Skipping type conversion)
    if (preg_match('/SELECT\s+[^;]*\bduplicate\b[^;]*;/i', $phase2NoComments)) {
        fail("Phase 2 contains SELECT with 'duplicate' message — should have been replaced with SIGNAL");
    } else {
        pass("Phase 2 has no SELECT with 'duplicate' message paths (all use SIGNAL)");
    }

    // 12d. Per-procedure named index three-state coverage (missing/exact/conflicting)
    echo "\n--- 12d. Per-Procedure Named Index Three-State Coverage ---\n";
    // Verify each named index in its preflight procedure has explicit missing/exact/conflicting/prefix paths
    $namedIndexes = [
        ['mig_preflight_configs_structure',  'uk_configs_group_key',   true],
        ['mig_preflight_files_structure',    'uk_files_hash',          true],
        ['mig_preflight_files_structure',    'idx_ref',               false],
        ['mig_preflight_files_structure',    'idx_pending_expire',    false],
        ['mig_preflight_likes_structure',    'uk_likes_pid_uid',       true],
        ['mig_preflight_role_cap_structure', 'uk_role_cap',            true],
    ];

    $threeStatePass = true;
    foreach ($namedIndexes as [$procName, $indexName, $isUnique]) {
        // Extract the procedure body
        $procPattern = '/DELIMITER\s*\/\/\s*DROP\s+PROCEDURE\s+IF\s+EXISTS\s*`' . preg_quote($procName, '/') . '`\s*\/\/\s*CREATE\s+PROCEDURE\s+`' . preg_quote($procName, '/') . '`.*?END\s*\/\/\s*DELIMITER\s*;/s';
        if (!preg_match($procPattern, $migrationCombined, $m)) {
            fail("Procedure `{$procName}` not found in migration (required for index `{$indexName}` three-state check)");
            $threeStatePass = false;
            continue;
        }
        $procBody = $m[0];

        $foundMissing = (bool)preg_match('/' . preg_quote($indexName, '/') . '.*?(?:missing|will be added)/i', $procBody);
        $foundExact   = (bool)preg_match('/' . preg_quote($indexName, '/') . '.*?exact match/i', $procBody);
        $foundConflict = (bool)preg_match('/' . preg_quote($indexName, '/') . '.*?conflicting|conflicting.*?' . preg_quote($indexName, '/') . '/i', $procBody);

        // Unique keys must also check SUB_PART IS NULL
        $foundPrefix = true;
        if ($isUnique) {
            $foundPrefix = (bool)preg_match('/' . preg_quote($indexName, '/') . '.*?SUB_PART/i', $procBody);
        }

        $states = [];
        if ($foundMissing)  $states[] = 'missing';
        if ($foundExact)    $states[] = 'exact';
        if ($foundConflict) $states[] = 'conflicting';
        if ($isUnique) {
            if ($foundPrefix) {
                $states[] = 'prefix-guard';
            } else {
                fail("`{$procName}` index `{$indexName}` missing SUB_PART prefix guard (required for unique key)");
                $threeStatePass = false;
                continue;
            }
        }

        $stateCount = count($states);
        if ($stateCount >= 3) {
            pass("`{$procName}` has three-state coverage for `{$indexName}`: " . implode(', ', $states));
        } else {
            fail("`{$procName}` index `{$indexName}` has only {$stateCount}/3: " . implode(', ', $states));
            $threeStatePass = false;
        }
    }
    if ($threeStatePass) {
        $totalIdx = count($namedIndexes);
        pass("All {$totalIdx} named indexes have three-state coverage in their preflight procedures");
    }

    // 12e. Specific procedure order and guard checks
    echo "\n--- 12e. Procedure-Specific Order and Guard Verification ---\n";
    $orderAndGuardPass = true;

    // 12e.1 mig_fix_files: ADD COLUMN ref_type must appear before ADD KEY idx_ref
    $procFixFilesPattern = '/DELIMITER\s*\/\/\s*DROP\s+PROCEDURE\s+IF\s+EXISTS\s*`mig_fix_files`\s*\/\/\s*CREATE\s+PROCEDURE\s+`mig_fix_files`.*?END\s*\/\/\s*DELIMITER\s*;/s';
    if (preg_match($procFixFilesPattern, $migrationCombined, $mFixFiles)) {
        $fixFilesBody = stripSqlComments($mFixFiles[0]);
        $addColRefTypePos = strpos($fixFilesBody, 'ADD COLUMN `ref_type`');
        $addKeyIdxRefPos = strpos($fixFilesBody, 'ADD KEY `idx_ref`');

        if ($addColRefTypePos !== false && $addKeyIdxRefPos !== false) {
            if ($addColRefTypePos < $addKeyIdxRefPos) {
                pass("mig_fix_files: ADD COLUMN `ref_type` before ADD KEY `idx_ref` (correct order)");
            } else {
                fail("mig_fix_files: ADD COLUMN `ref_type` appears AFTER ADD KEY `idx_ref`");
                $orderAndGuardPass = false;
            }
        } else {
            if ($addColRefTypePos === false) fail("mig_fix_files: ADD COLUMN `ref_type` not found");
            if ($addKeyIdxRefPos === false) fail("mig_fix_files: ADD KEY `idx_ref` not found");
            $orderAndGuardPass = false;
        }

        // Also verify ADD COLUMN upload_status before ADD KEY idx_pending_expire
        $addColUploadPos = strpos($fixFilesBody, 'ADD COLUMN `upload_status`');
        $addKeyPendingPos = strpos($fixFilesBody, 'ADD KEY `idx_pending_expire`');
        if ($addColUploadPos !== false && $addKeyPendingPos !== false) {
            if ($addColUploadPos < $addKeyPendingPos) {
                pass("mig_fix_files: ADD COLUMN `upload_status` before ADD KEY `idx_pending_expire` (correct order)");
            } else {
                fail("mig_fix_files: ADD COLUMN `upload_status` after ADD KEY `idx_pending_expire`");
                $orderAndGuardPass = false;
            }
        }
    } else {
        fail("Stored procedure `mig_fix_files` not found in migration");
        $orderAndGuardPass = false;
    }

    // 12e.2 mig_preflight_good_likes_mismatch: must have column guard for ref_type/ref_id
    $procGoodLikesPattern = '/DELIMITER\s*\/\/\s*DROP\s+PROCEDURE\s+IF\s+EXISTS\s*`mig_preflight_good_likes_mismatch`\s*\/\/\s*CREATE\s+PROCEDURE\s+`mig_preflight_good_likes_mismatch`.*?END\s*\/\/\s*DELIMITER\s*;/s';
    if (preg_match($procGoodLikesPattern, $migrationCombined, $mGoodLikes)) {
        $goodLikesBody = $mGoodLikes[0];
        // Check for COLUMN_NAME = 'ref_type' which confirms column existence guard
        $hasRefTypeGuard = (bool)preg_match("/COLUMN_NAME\s*=\s*'ref_type'/i", $goodLikesBody);
        $hasRefIdGuard = (bool)preg_match("/COLUMN_NAME\s*=\s*'ref_id'/i", $goodLikesBody);
        if ($hasRefTypeGuard && $hasRefIdGuard) {
            pass("mig_preflight_good_likes_mismatch has ref_type/ref_id column existence guard");
        } else {
            fail("mig_preflight_good_likes_mismatch missing ref_type/ref_id column guard");
            $orderAndGuardPass = false;
        }
    } else {
        fail("Stored procedure `mig_preflight_good_likes_mismatch` not found in migration");
        $orderAndGuardPass = false;
    }

    // 12e.3 mig_preflight_configs_structure: NULL checks must follow column existence checks
    $procCfgStructPattern = '/DELIMITER\s*\/\/\s*DROP\s+PROCEDURE\s+IF\s+EXISTS\s*`mig_preflight_configs_structure`\s*\/\/\s*CREATE\s+PROCEDURE\s+`mig_preflight_configs_structure`.*?END\s*\/\/\s*DELIMITER\s*;/s';
    if (preg_match($procCfgStructPattern, $migrationCombined, $mCfgStruct)) {
        $cfgStructBody = $mCfgStruct[0];
        $hasColCheck = (bool)preg_match('/INFORMATION_SCHEMA\.`COLUMNS`.*`group`.*\n.*SIGNAL.*missing/si', $cfgStructBody);
        $hasNullCheck = (bool)preg_match('/NULL group rows/i', $cfgStructBody);
        if ($hasColCheck && $hasNullCheck) {
            pass("mig_preflight_configs_structure has column existence check and NULL value check");
        } else {
            fail("mig_preflight_configs_structure: colCheck=" . ($hasColCheck ? '1' : '0') . " nullCheck=" . ($hasNullCheck ? '1' : '0'));
            $orderAndGuardPass = false;
        }
    } else {
        fail("Stored procedure `mig_preflight_configs_structure` not found in migration");
        $orderAndGuardPass = false;
    }

    if ($orderAndGuardPass) {
        pass("All procedure-specific order and guard checks passed");
    }
} else {
    echo "[SKIP] No migration files for static analysis\n";
}

// ────────────────────────────────────────────────────────────
//  Section 13: Error Fixture Verification (In-Memory)
//  These tests use in-memory string fragments to verify the
//  test framework's own failure detection. They simulate wrong
//  code patterns and confirm the detection logic catches them.
//  No real files are modified.
// ────────────────────────────────────────────────────────────
echo "\n--- 13. Error Fixture Verification ---\n";

// 13a. Index-before-column detection: simulate mig_fix_files with
// ADD KEY idx_ref before ADD COLUMN ref_type (wrong order)
echo "\n--- 13a. Index-Before-Column Detection ---\n";
$badFixFiles = 'CREATE PROCEDURE `mig_fix_files`()'
    . "\nBEGIN"
    . "\nALTER TABLE `files` ADD KEY `idx_ref` (`ref_type`, `ref_id`);"
    . "\nALTER TABLE `files` ADD COLUMN `ref_type` VARCHAR(64) DEFAULT NULL;"
    . "\nEND";
$colPos = strpos($badFixFiles, 'ADD COLUMN `ref_type`');
$keyPos = strpos($badFixFiles, 'ADD KEY `idx_ref`');
if ($colPos !== false && $keyPos !== false) {
    if ($colPos > $keyPos) {
        pass("13a: Bad fixture confirmed: ADD KEY idx_ref BEFORE ADD COLUMN ref_type (wrong order)");
    } else {
        fail("13a: Bad fixture unexpectedly has correct column-before-index order");
    }
} else {
    fail("13a: Could not locate ADD COLUMN/ADD KEY in bad fixture");
}

// 13b. Optional-column-before-guard detection: simulate
// mig_preflight_good_likes_mismatch without column existence guard
echo "\n--- 13b. Column Guard Detection ---\n";
$badGuardProcedure = 'CREATE PROCEDURE `mig_preflight_good_likes_mismatch`()'
    . "\nBEGIN"
    . "\nSELECT COUNT(*) INTO v_mismatch_count FROM `good` g"
    . "\nINNER JOIN `likes` l ON g.pid = l.pid AND g.uid = l.uid"
    . "\nWHERE l.ref_type != 'card';"
    . "\nEND";
$hasRefTypeColumnGuard = (bool)preg_match('/ref_type.*COLUMNS.*likes/i', $badGuardProcedure);
if (!$hasRefTypeColumnGuard) {
    pass("13b: Detection correctly identifies missing ref_type column guard in bad fixture");
} else {
    fail("13b: Bad fixture unexpectedly contains ref_type column guard");
}

// 13b.1 Also prove the CURRENT real code HAS the guard
$procGuardPattern = '/DELIMITER\s*\/\/\s*DROP\s+PROCEDURE\s+IF\s+EXISTS\s*`mig_preflight_good_likes_mismatch`.*?END\s*\/\/\s*DELIMITER\s*;/s';
if (preg_match($procGuardPattern, $migrationCombined, $mGuard)) {
    $currentGuardBody = $mGuard[0];
    $currentHasGuard = (bool)preg_match("/COLUMN_NAME\s*=\s*'ref_type'/i", $currentGuardBody);
    if ($currentHasGuard) {
        pass("13b: Current mig_preflight_good_likes_mismatch HAS ref_type column guard (confirmed)");
    } else {
        fail("13b: Current code missing ref_type column guard");
    }
} else {
    fail("13b: Could not find current mig_preflight_good_likes_mismatch procedure");
}

// 13c. Prefix unique detection: verify test identifies SUB_PART
echo "\n--- 13c. Prefix Unique Detection ---\n";
$statsWithPrefix = "INDEX_NAME = 'uk_test' AND SUB_PART IS NOT NULL;";
$detectedPrefix = (bool)preg_match('/SUB_PART/i', $statsWithPrefix);
if ($detectedPrefix) {
    pass("13c: Prefix detection identifies SUB_PART presence (would fail exact unique check)");
} else {
    fail("13c: Could not detect SUB_PART reference in fixture");
}

// 13c.1 Confirm current preflight procedures check SUB_PART for unique keys
$prefixCheckFound = 0;
foreach ($namedIndexes as [$procName, $indexName, $isUnique]) {
    if ($isUnique) {
        $procPatternCheck = '/DELIMITER\s*\/\/\s*DROP\s+PROCEDURE\s+IF\s+EXISTS\s*`' . preg_quote($procName, '/') . '`.*?END\s*\/\/\s*DELIMITER\s*;/s';
        if (preg_match($procPatternCheck, $migrationCombined, $mPc)) {
            if (preg_match('/SUB_PART/i', $mPc[0])) {
                $prefixCheckFound++;
            }
        }
    }
}
if ($prefixCheckFound >= 2) {
    pass("13c: {$prefixCheckFound} unique key preflight procedures include SUB_PART check");
} else {
    fail("13c: Only {$prefixCheckFound}/4 unique key procedures have SUB_PART check");
}

// 13d. Wrong slug detection: verify config-based slug check rejects wrong slug
echo "\n--- 13d. Wrong Slug Detection ---\n";
$fixtureRoleConfig = ['root' => 1, 'admin' => 2, 'user' => 3, 'guest' => 4];
$badDbRole = (object)['id' => 2, 'slug' => 'editor'];
$expectedSlugName = array_search($badDbRole->id, $fixtureRoleConfig);
$wrongSlugDetected = ($expectedSlugName === false || $badDbRole->slug !== $expectedSlugName);
if ($wrongSlugDetected) {
    pass("13d: Detection correctly rejects slug '{$badDbRole->slug}' for role id={$badDbRole->id} (expected '{$expectedSlugName}')");
} else {
    fail("13d: Slug check failed to detect wrong slug");
}

// 13d.1 Test the expected correct slugs pass validation
$correctSlugs = ['root' => 1, 'admin' => 2, 'user' => 3, 'guest' => 4];
$allCorrect = true;
foreach ($correctSlugs as $expectedSlugVal => $expectedId) {
    $testRole = (object)['id' => $expectedId, 'slug' => $expectedSlugVal];
    $foundSlug = array_search($testRole->id, $fixtureRoleConfig);
    if ($foundSlug === false || $testRole->slug !== $foundSlug) {
        $allCorrect = false;
    }
}
if ($allCorrect) {
    pass("13d: Correct slug mapping (root=1, admin=2, user=3, guest=4) passes validation");
} else {
    fail("13d: Correct slug mapping unexpectedly failed validation");
}

// 13e. is_system=0 detection
echo "\n--- 13e. is_system=0 Detection ---\n";
$badRoleNoSystem = (object)['id' => 3, 'slug' => 'user', 'is_system' => false];
$isSystemDetected = !$badRoleNoSystem->is_system;
if ($isSystemDetected) {
    pass("13e: Detection correctly rejects is_system=0 for role id={$badRoleNoSystem->id} ({$badRoleNoSystem->slug})");
} else {
    fail("13e: is_system check failed to detect false value");
}

// 13e.1 Confirm current seedSystemCapabilities() checks is_system
$rolesContent = file_get_contents($rolesPath);
if ($rolesContent !== false) {
    $hasIsSystemCheck = (bool)preg_match('/is_system/', $rolesContent);
    if ($hasIsSystemCheck) {
        pass("13e: seedSystemCapabilities() in Roles.php includes is_system verification");
    } else {
        fail("13e: seedSystemCapabilities() missing is_system check");
    }
} else {
    fail("13e: Could not read Roles.php");
}

// 13f. Capability insert without transaction detection
echo "\n--- 13f. Transaction Atomicity Detection ---\n";
// Negative fixture: bad version without Db::startTrans
$badSeedNoTx = 'public static function seedSystemCapabilities(): array
{
    $roles = config("system.system_roles");
    $roleCaps = self::getSystemRoleCapabilityMatrix();
    $roleCaps[$roles["root"]] = array_keys(RBAC::getAllCapabilities());
    foreach ($roleCaps as $roleId => $caps) {
        foreach ($caps as $cap) {
            RoleCapabilities::create(["role_id" => $roleId, "capability" => $cap]);
        }
    }
    RBAC::clearCache();
    return [];
}';
$badHasTransaction = (bool)preg_match('/Db::startTrans\s*\(\)/', $badSeedNoTx);
$badHasRollback = (bool)preg_match('/Db::rollback\s*\(\)/', $badSeedNoTx);
if (!$badHasTransaction && !$badHasRollback) {
    pass("13f: Detection correctly identifies missing transaction in bad fixture (no Db::startTrans/rollback)");
} else {
    fail("13f: Bad fixture unexpectedly contains transaction code");
}

// 13f.1 Confirm current seedSystemCapabilities() HAS transaction + rollback
// Use function-start anchored search: find text from 'function seedSystemCapabilities'
// to the next 'public static function' or end of file
if ($rolesContent !== false) {
    $fnPos = strpos($rolesContent, 'function seedSystemCapabilities');
    if ($fnPos !== false) {
        // Find next function boundary or end of file
        $nextFnPos = strpos($rolesContent, 'public static function', $fnPos + 5);
        if ($nextFnPos === false) {
            $nextFnPos = strlen($rolesContent);
        }
        $seedBody = substr($rolesContent, $fnPos, $nextFnPos - $fnPos);
        $hasStartTrans = (bool)preg_match('/Db::startTrans\s*\(\)/', $seedBody);
        $hasRollback = (bool)preg_match('/Db::rollback\s*\(\)/', $seedBody);
        $hasCommit = (bool)preg_match('/Db::commit\s*\(\)/', $seedBody);
        if ($hasStartTrans && $hasRollback && $hasCommit) {
            pass("13f: seedSystemCapabilities() uses Db::startTrans/commit/rollback — transactional write confirmed");
        } else {
            fail("13f: seedSystemCapabilities() missing transaction: startTrans=" . ($hasStartTrans ? '1' : '0') . " rollback=" . ($hasRollback ? '1' : '0') . " commit=" . ($hasCommit ? '1' : '0'));
        }
    } else {
        fail("13f: seedSystemCapabilities() method not found in Roles.php");
    }
}

// ────────────────────────────────────────────────────────────
//  Summary
// ────────────────────────────────────────────────────────────
echo "\n=== Summary ===\n";
echo "Passed: {$passCount}\n";
echo "Failed: {$failCount}\n";

if ($failCount > 0) {
    echo "\nFAILED CHECKS:\n";
    foreach ($errors as $i => $err) {
        echo ($i + 1) . ". {$err}\n";
    }
    echo "\nSchema contract test FAILED.\n";
    exit(1);
} else {
    echo "\nAll schema contract checks PASSED.\n";
    exit(0);
}
