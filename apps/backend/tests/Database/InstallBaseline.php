<?php
declare(strict_types=1);

// ============================================================
// LoveCardsV3 — Install Baseline Test (Static Analysis)
//
// Independent PHP script, no PHPUnit dependency.
// Validates the Installer and Database utility contracts
// without connecting to a real database.
//
// Usage: @php tests/Database/InstallBaseline.php
// Exit code: 0 = PASS, 1 = FAIL
// ============================================================

$baseDir = dirname(__DIR__, 2);  // apps/backend/

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

// ── Helper: strip SQL comments ─────────────────────────────────
function stripSqlComments(string $sql): string
{
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    $sql = preg_replace('/--[^\n]*/', '', $sql);
    return $sql;
}

echo "=== Install Baseline Test ===\n\n";

// ────────────────────────────────────────────────────────────
//  1. Database.php: Connect() 不使用 admin 表
// ────────────────────────────────────────────────────────────
echo "--- 1. Database::Connect() ---\n";

$dbPath = $baseDir . '/app/modules/Operations/Installer/Infrastructure/Database.php';
if (!file_exists($dbPath)) {
    fail("Database.php not found at {$dbPath}");
    exit(1);
}

$dbCode = file_get_contents($dbPath);
if ($dbCode === false) {
    fail("Failed to read Database.php");
    exit(1);
}

// Check that Connect() does NOT reference 'admin' table
if (preg_match('/->table\(\s*\'admin\'\s*\)/', $dbCode)) {
    fail("Database::Connect() still references 'admin' table — empty database would fail");
} else {
    pass("Database::Connect() does not reference 'admin' table");
}

// Check that Connect() uses 'SELECT 1' or similar generic query
if (preg_match('/SELECT\s+1/i', $dbCode) || preg_match('/query\(\s*\'SELECT\b/', $dbCode)) {
    pass("Database::Connect() uses a generic connection check (SELECT 1 or similar)");
} else {
    fail("Database::Connect() may not use a generic connection check");
}

if (preg_match("/'charset'\\s*=>\\s*'utf8mb4'/", $dbCode)) {
    pass("Database::Connect() uses utf8mb4 for multilingual data");
} else {
    fail("Database::Connect() must use utf8mb4, not the legacy three-byte utf8 charset");
}

// ────────────────────────────────────────────────────────────
//  2. Database.php: Clear() 返回 bool
// ────────────────────────────────────────────────────────────
echo "\n--- 2. Database::Clear() ---\n";

// Check return type declaration
if (preg_match('/function\s+Clear\s*\([^)]*\)\s*:\s*bool/', $dbCode)) {
    pass("Database::Clear() declares ': bool' return type");
} else {
    fail("Database::Clear() does not declare ': bool' return type");
}

// Check that Clear() returns true/false, not array
if (preg_match('/return\s+(true|false)\s*;/', $dbCode)) {
    pass("Database::Clear() returns bool value");
} else {
    // Clear() might throw on error, only return true on success
    if (preg_match('/return\s+true\s*;/', $dbCode)) {
        pass("Database::Clear() returns true on success, throws on failure");
    } else {
        fail("Database::Clear() does not appear to return bool");
    }
}

// ────────────────────────────────────────────────────────────
//  3. Database.php: ImportSQLFile() 返回 void
// ────────────────────────────────────────────────────────────
echo "\n--- 3. Database::ImportSQLFile() ---\n";

if (preg_match('/function\s+ImportSQLFile\s*\([^)]*\)\s*:\s*void/', $dbCode)) {
    pass("Database::ImportSQLFile() declares ': void' return type");
} else {
    fail("Database::ImportSQLFile() does not declare ': void' return type");
}

// Verify ImportSQLFile() has no return statement with a value
$importMethod = '';
if (preg_match('/function\s+ImportSQLFile\(.*?\{.*?\n(.*?)\n\t\}/s', $dbCode, $importMatch)) {
    $importBody = $importMatch[1];
} else {
    // Simpler approach: count returns in the method section
    $importMethod = '';
}

// ────────────────────────────────────────────────────────────
//  4. Install.php: PostInstallLock() 先 Config init 再锁
// ────────────────────────────────────────────────────────────
echo "\n--- 4. PostInstallLock() ordering ---\n";

$installPath = $baseDir . '/app/system/controller/Install.php';
$installCode = file_get_contents($installPath);
if ($installCode === false) {
    fail("Failed to read Install.php");
    exit(1);
}
$exportCode = (string) file_get_contents($baseDir . '/app/modules/Operations/Installer/Transport/System/Export.php');
$jwtCode = (string) file_get_contents($baseDir . '/app/modules/Platform/Auth/Infrastructure/Jwt.php');

// Extract PostInstallLock() method body first to check lock order
$postInstallLockBody = '';
if (preg_match('/function\s+PostInstallLock\s*\(\s*\)\s*\n?\s*\{((?:[^{}]*|\{(?:[^{}]*|\{[^{}]*\})*\})*)\}/s', $installCode, $m)) {
    $postInstallLockBody = $m[1];
}

// Check that ConfigService::init() appears BEFORE Roles::seedSystemCapabilities() BEFORE InstallLock()
$initPos = strpos($postInstallLockBody, 'ConfigService::init()');
$seedPos = strpos($postInstallLockBody, 'seedSystemCapabilities');
$lockPos = strpos($postInstallLockBody, 'Common::InstallLock()');

if ($initPos !== false && $seedPos !== false && $lockPos !== false) {
    if ($initPos < $seedPos && $seedPos < $lockPos) {
        pass("PostInstallLock() order: ConfigService::init() -> seedSystemCapabilities() -> Common::InstallLock()");
    } elseif ($initPos < $lockPos) {
        fail("PostInstallLock(): seedSystemCapabilities() not found between ConfigService::init() and Common::InstallLock()");
    } else {
        fail("PostInstallLock(): ConfigService::init() is called AFTER InstallLock() — init failure would leave lock");
    }
} elseif ($initPos === false) {
    fail("ConfigService::init() not found in PostInstallLock()");
} elseif ($seedPos === false) {
    fail("Roles::seedSystemCapabilities() not found in PostInstallLock() — clean-install capability would be empty");
} else {
    fail("Common::InstallLock() not found in PostInstallLock() body");
}

// ────────────────────────────────────────────────────────────
//  5. Install.php: PostCreateRsa() 有安装锁 guard
// ────────────────────────────────────────────────────────────
echo "\n--- 5. PostCreateRsa() guard ---\n";

// Check PostCreateRsa has CheckInstallLock guard at the start
if (preg_match('/PostCreateRsa\s*\(\s*\).*?\{.*?CheckInstallLock\s*\(/s', $installCode)) {
    pass("PostCreateRsa() has CheckInstallLock guard");
} else {
    // More specific check
    if (preg_match('/PostCreateRsa\s*\(\)\s*\n?\s*\{[^}]*CheckInstallLock/s', $installCode) ||
        preg_match('/PostCreateRsa\s*\(\s*\)[^}]*CheckInstallLock/s', $installCode)) {
        pass("PostCreateRsa() has CheckInstallLock guard");
    } else {
        fail("PostCreateRsa() does not have CheckInstallLock guard — JWT key could be overwritten after install");
    }
}

// ────────────────────────────────────────────────────────────
//  6. Install.php: PostDbConfig() 正确调用 Clear/ImportSQLFile
// ────────────────────────────────────────────────────────────
echo "\n--- 6. PostDbConfig() call patterns ---\n";

// Check that Database::Clear() result is NOT treated as array with ['status']
if (preg_match('/\$result\[\'status\'\]/', $installCode)) {
    // Check if this is inside PostDbConfig or another context
    $clearSection = '';
    if (preg_match('/Clear\s*\(\s*\).*?\n.*?\$result\[\'status\'\]/s', $installCode)) {
        fail("PostDbConfig still reads Clear() result as array with ['status']");
    } else {
        pass("PostDbConfig does not treat Clear() result as array");
    }
} else {
    pass("No \$result['status'] pattern in Install.php (Clear/ImportSQLFile correctly handled)");
}

// Check that ImportSQLFile() is called with try-catch
if (preg_match('/try\s*\{[^}]*ImportSQLFile\s*\(/s', $installCode)) {
    pass("ImportSQLFile() is called within try-catch block");
} else {
    fail("ImportSQLFile() may not be within try-catch block — exceptions would propagate unhandled");
}

// Check that Database::Connect() is called with try-catch
if (preg_match('/try\s*\{[^}]*Database::Connect\s*\(/s', $installCode)) {
    pass("Database::Connect() is called within try-catch block");
} else {
    fail("Database::Connect() may not be within try-catch block");
}

// ────────────────────────────────────────────────────────────
//  7. data.sql: 无 fk_role_permissions_permission
// ────────────────────────────────────────────────────────────
echo "\n--- 7. data.sql foreign key ---\n";

$dataSqlPath = $baseDir . '/data.sql';
$dataSql = file_get_contents($dataSqlPath);
if ($dataSql === false) {
    fail("Failed to read data.sql");
} else {
    if (preg_match('/fk_role_permissions_permission/i', stripSqlComments($dataSql))) {
        fail("data.sql still contains fk_role_permissions_permission — import would fail with orphan FK");
    } else {
        pass("data.sql does not contain fk_role_permissions_permission");
    }

    if (preg_match('/`\w+`\s+TIMESTAMP\s+NOT\s+NULL(?!\s+DEFAULT)/i', stripSqlComments($dataSql))) {
        fail("data.sql contains TIMESTAMP NOT NULL without an explicit default");
    } else {
        pass("data.sql timestamp defaults are explicit and MySQL 5.7 strict-mode safe");
    }
}

// ────────────────────────────────────────────────────────────
//  8. PHP 支持契约一致性
// ────────────────────────────────────────────────────────────
echo "\n--- 8. PHP support contract ---\n";

if (preg_match('/public\s+function\s+__construct\s*\([^)]*\).*?Export::Create/s', $installCode)) {
    fail("Install constructor attempts to return an HTTP response");
} else {
    pass("Install constructor does not contain an ineffective response guard");
}
if (preg_match('/public\s+static\s+function\s+Create\s*\(/', $exportCode)) {
    pass("Installer Export::Create response helper exists");
} else {
    fail("Installer calls missing Export::Create response helper");
}
if (file_exists($baseDir . '/lock.txt')) {
    fail("Release source contains a default install lock");
} else {
    pass("Release source does not ship an install lock");
}
if (strpos($installCode, 'use app\\modules\\Platform\\Auth\\Public\\SigningKeys;') !== false
    && strpos($installCode, 'Auth\\Infrastructure\\Jwt') === false) {
    pass("Installer uses the public Auth signing key capability");
} else {
    fail("Installer must use public Auth signing key capability");
}
if (strpos($jwtCode, '[$jwt_config[\'alg\']]') === false) {
    pass("JWT decode calls match the JWT 7 method signature");
} else {
    fail("JWT decode still passes the removed JWT 6 algorithm argument");
}

$composer = json_decode((string) file_get_contents($baseDir . '/composer.json'), true);
$systemConfig = (string) file_get_contents($baseDir . '/config/system.php');
$installCode = (string) file_get_contents($baseDir . '/app/system/controller/Install.php');
$versionCode = (string) file_get_contents($baseDir . '/app/modules/Operations/Version/Service/VersionService.php');
$environmentCode = (string) file_get_contents($baseDir . '/app/modules/Operations/Installer/Infrastructure/Environment.php');

if (($composer['require']['php'] ?? null) === '>=8.1 <9.0') {
    pass('Composer requires PHP >=8.1 <9.0');
} else {
    fail('Composer PHP requirement is inconsistent');
}
foreach ([$systemConfig, $installCode, $versionCode] as $source) {
    if (strpos($source, '8.1.0') === false || strpos($source, '9.0.0') === false) {
        fail('Runtime PHP support bounds are inconsistent');
        break;
    }
}
if (strpos($environmentCode, 'version_compare') !== false) {
    pass('Installer compares PHP versions with version_compare');
} else {
    fail('Installer does not use version_compare');
}

// ────────────────────────────────────────────────────────────
//  9. Passwordless local MySQL fallback matches .env.example
// ────────────────────────────────────────────────────────────
echo "\n--- 9. Database environment fallback ---\n";

$databaseConfig = (string) file_get_contents($baseDir . '/config/database.php');
$envExample = (string) file_get_contents($baseDir . '/.env.example');
if (preg_match('/\'password\'\s*=>\s*env\(\'database\.password\',\s*\$persistent\[\'password\'\]\s*\?\?\s*\'\'\)/', $databaseConfig)) {
    pass('Database config prefers environment, then persistent state, while permitting passwordless local MySQL');
} else {
    fail('Database config must preserve environment > persistent > passwordless fallback precedence');
}
if (preg_match('/^PASSWORD\\s*=\\s*$/m', $envExample)) {
    pass('.env.example documents the passwordless local MySQL fallback');
} else {
    fail('.env.example database password contract changed unexpectedly');
}

// ────────────────────────────────────────────────────────────
//  安装器 GET 端点必须在安装锁之后（防环境/版本信息泄露与安装后 GitHub 外呼）
// ────────────────────────────────────────────────────────────
echo "--- Install controller GET endpoints behind install lock ---\n";
foreach (['GetVersionInfo', 'GetCheckEnvironment'] as $lockedAction) {
    // 方法体起点到下一个方法声明之间必须出现 CheckInstallLock
    if (preg_match('/function\s+' . $lockedAction . '\s*\([^)]*\)\s*:\s*[\w\\\\]+\s*\{([\s\S]*?)(?=\n\s*(?:public|private|protected)\s+function|\z)/', $installCode, $mm) === 1
        && str_contains($mm[1], 'CheckInstallLock')) {
        pass("Install::{$lockedAction}() is guarded by the install lock");
    } elseif (preg_match('/function\s+' . $lockedAction . '\s*\(\s*\)[\s\S]*?\n    \}/', $installCode, $mm) === 1
        && str_contains($mm[0], 'CheckInstallLock')) {
        pass("Install::{$lockedAction}() is guarded by the install lock");
    } else {
        fail("Install::{$lockedAction}() lost its install lock guard");
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
    echo "\nInstall baseline test FAILED.\n";
    exit(1);
} else {
    echo "\nAll install baseline checks PASSED.\n";
    exit(0);
}
