<?php

declare(strict_types=1);

$mode = $argv[1] ?? 'create';
$database = $argv[2] ?? '';

// The bundled Windows PHP runtime may not inherit an OpenSSL configuration
// path. RSA generation otherwise fails with "configuration file routines";
// resolve the bundled fixture config without changing production settings.
if (!is_string(getenv('OPENSSL_CONF')) || trim((string) getenv('OPENSSL_CONF')) === '') {
    $bundledOpenSsl = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
    if (is_file($bundledOpenSsl)) putenv('OPENSSL_CONF=' . $bundledOpenSsl);
}

if (getenv('LOVECARDS_E2E_ISOLATED') !== '1') {
    fwrite(STDERR, "Refusing E2E database operation without LOVECARDS_E2E_ISOLATED=1\n");
    exit(1);
}
if (!preg_match('/^lovecards_e2e_[a-z0-9_]+$/', $database)) {
    fwrite(STDERR, "Refusing E2E database name: {$database}\n");
    exit(1);
}

$host = getenv('LOVECARDS_E2E_DB_HOST') ?: '127.0.0.1';
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "Refusing non-local E2E database host: {$host}\n");
    exit(1);
}
$instanceRoot = trim((string) (getenv('LC_INSTANCE_ROOT') ?: ''));
if ($mode === 'create') {
    if ($instanceRoot === '' || strpos($instanceRoot, "\0") !== false) {
        fwrite(STDERR, "Refusing E2E bootstrap without an isolated LC_INSTANCE_ROOT\n");
        exit(1);
    }
    $instanceRoot = str_replace('\\', '/', $instanceRoot);
    if (!preg_match('#^(?:[A-Za-z]:/|/|//)#', $instanceRoot)) {
        fwrite(STDERR, "Refusing non-absolute E2E instance root\n");
        exit(1);
    }
    $instanceRoot = rtrim($instanceRoot, '/');
    $sourceRoot = str_replace('\\', '/', realpath(__DIR__ . '/../apps/backend') ?: (__DIR__ . '/../apps/backend'));
    $normalizedInstance = strtolower($instanceRoot);
    $normalizedSource = strtolower(rtrim($sourceRoot, '/'));
    if ($normalizedInstance === $normalizedSource || str_starts_with($normalizedInstance, $normalizedSource . '/')) {
        fwrite(STDERR, "Refusing E2E instance root inside active application code\n");
        exit(1);
    }
    $stateRoot = $instanceRoot . '/shared/state';
    if (is_link($stateRoot) || (!is_dir($stateRoot) && !mkdir($stateRoot, 0700, true) && !is_dir($stateRoot))) {
        fwrite(STDERR, "Unable to create isolated state directory\n");
        exit(1);
    }
    if (!is_string(getenv('RANDFILE')) || trim((string) getenv('RANDFILE')) === '') {
        putenv('RANDFILE=' . $stateRoot . '/openssl.rand');
    }
    $randFile = (string) getenv('RANDFILE');
    if ($randFile !== '' && !file_exists($randFile) && file_put_contents($randFile, '') === false) {
        fwrite(STDERR, "Unable to create isolated OpenSSL random state\n");
        exit(1);
    }
}
$port = getenv('LOVECARDS_E2E_DB_PORT') ?: '3306';
$user = getenv('LOVECARDS_E2E_DB_USER') ?: 'root';
$password = getenv('LOVECARDS_E2E_DB_PASSWORD') ?: '';
$runtimeDatabaseEnv = [
    'DATABASE_DRIVER' => 'mysql',
    'DATABASE_TYPE' => 'mysql',
    'DATABASE_HOSTNAME' => $host,
    'DATABASE_HOSTPORT' => $port,
    'DATABASE_DATABASE' => $database,
    'DATABASE_USERNAME' => $user,
    'DATABASE_PASSWORD' => $password,
    'PHP_DATABASE_DRIVER' => 'mysql',
    'PHP_DATABASE_TYPE' => 'mysql',
    'PHP_DATABASE_HOSTNAME' => $host,
    'PHP_DATABASE_HOSTPORT' => $port,
    'PHP_DATABASE_DATABASE' => $database,
    'PHP_DATABASE_USERNAME' => $user,
    'PHP_DATABASE_PASSWORD' => $password,
];
foreach ($runtimeDatabaseEnv as $key => $value) {
    putenv($key . '=' . $value);
}
// 关键隔离：框架 Env 里 .env 文件优先于进程环境变量。开发仓库存在 .env 时，
// 下方 think lc:system:migrate 子进程会连到 .env 指向的库（开发库）而非这里的
// 一次性 e2e 库。LC_ENV_NAME 让 think/index 入口的 loadEnv 只读取不存在的
// .env.e2e，迁移因此严格使用上面 putenv 的 e2e 连接参数（CI 裸检出无 .env，行为不变）。
putenv('LC_ENV_NAME=e2e');
$dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
$pdo = null;
$databaseCreated = false;
$quoted = '`' . str_replace('`', '``', $database) . '`';

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($mode === 'drop') {
        $pdo->exec("DROP DATABASE IF EXISTS {$quoted}");
        exit(0);
    }

    $exists = $pdo->prepare('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1');
    $exists->execute([$database]);
    if ($exists->fetchColumn() !== false) {
        throw new RuntimeException('Refusing to reuse an existing E2E database: ' . $database);
    }
    $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $databaseCreated = true;
    $databasePdo = new PDO("{$dsn};dbname={$database}", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $sql = file_get_contents(__DIR__ . '/../apps/backend/data.sql');
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('apps/backend/data.sql is missing or empty');
    }
    $databasePdo->exec($sql);

    // E2E 数据库必须与安装后的运行时一致：基础 data.sql 之后还要推进
    // 当前所有可执行迁移，否则认证会缺少 session 字段，社区表也不存在。
    $backend = __DIR__ . '/../apps/backend';
    $php = escapeshellarg(PHP_BINARY);
    $think = escapeshellarg($backend . '/think');
    $previousDirectory = getcwd();
    chdir($backend);
    try {
        $complete = false;
        $migrationFiles = glob($backend . '/database/migrations/*.sql') ?: [];
        $expectedMigrations = count($migrationFiles);
        if ($expectedMigrations < 1) {
            throw new RuntimeException('No database migrations found for E2E bootstrap');
        }
        // 单次命令只推进一个阶段；按迁移数量给出有界预算，不能在仓库增长后固定卡在 128 次。
        for ($attempt = 0; $attempt < $expectedMigrations * 8 + 1 && !$complete; $attempt++) {
            $lines = [];
            // This database is disposable and isolated. Complete the full
            // schema, including contract phases, so the subsequent E2E suite
            // starts from a terminal runtime state. Production migration
            // approval remains explicit through the release coordinator.
            $command = $php . ' ' . $think . ' lc:system:migrate --contract';
            exec($command, $lines, $exitCode);
            if ($exitCode !== 0) {
                throw new RuntimeException('E2E migration failed: ' . implode("\n", $lines));
            }

            // 每次 CLI 调用只推进一个 phase；不能仅根据本次输出的
            // 单条记录判断全库完成。必须以账本全表和迁移文件数量交叉确认。
            $ledgerCount = (int) $databasePdo
                ->query('SELECT COUNT(*) FROM `system_upgrade_steps`')
                ->fetchColumn();
            $openCount = (int) $databasePdo
                ->query("SELECT COUNT(*) FROM `system_upgrade_steps` WHERE `status` <> 'complete'")
                ->fetchColumn();
            $complete = $ledgerCount >= $expectedMigrations && $openCount === 0;
        }
        if (!$complete) {
            throw new RuntimeException('E2E migrations did not reach a terminal state');
        }

        // Isolated admin E2E runs must have the account requested by the
        // runner. This fixture is created only in the disposable database.
        $e2eAccount = trim((string) (getenv('LOVECARDS_E2E_ADMIN_ACCOUNT') ?: ''));
        $e2ePassword = (string) (getenv('LOVECARDS_E2E_ADMIN_PASSWORD') ?: '');
        // SDK 全量套件覆盖 root 专属能力；仅为新建的隔离夹具选择 root，普通 UI 验收仍用 admin。
        $e2eRoleId = getenv('LOVECARDS_E2E_RUN_SDK_INTEGRATION') === '1' ? 1 : 2;
        if ($e2eAccount !== '' && $e2ePassword !== '') {
            $e2eEmail = filter_var($e2eAccount, FILTER_VALIDATE_EMAIL) ? $e2eAccount : 'e2e-admin@example.test';
            $e2eUsername = filter_var($e2eAccount, FILTER_VALIDATE_EMAIL) ? 'e2e_admin' : $e2eAccount;
            $e2eUsername = substr($e2eUsername, 0, 32);
            $existing = $databasePdo->prepare('SELECT `id` FROM `users` WHERE `email` = ? OR `username` = ? LIMIT 1');
            $existing->execute([$e2eEmail, $e2eUsername]);
            $e2eUserId = (int) ($existing->fetchColumn() ?: 0);
            if ($e2eUserId === 0) {
                $e2eNumber = 'e2e' . bin2hex(random_bytes(12));
                $e2eUid = (string) random_int(100000000000, 999999999999);
                $insert = $databasePdo->prepare('INSERT INTO `users` (`uid`,`number`,`avatar`,`email`,`phone`,`username`,`password`,`status`,`roles_id`,`security_version`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                $now = date('Y-m-d H:i:s');
                $insert->execute([$e2eUid, $e2eNumber, '', $e2eEmail, '', $e2eUsername, password_hash($e2ePassword, PASSWORD_DEFAULT), 0, json_encode([$e2eRoleId]), 1, $now, $now]);
                $e2eUserId = (int) $databasePdo->lastInsertId();
                $registry = $databasePdo->prepare('INSERT INTO `user_uid_registry` (`uid`,`user_id`,`issued_at`) VALUES (?,?,?)');
                $registry->execute([$e2eUid, $e2eUserId, $now]);
                $role = $databasePdo->prepare('INSERT INTO `user_roles` (`user_id`,`role_id`,`created_at`) VALUES (?,?,?)');
                $role->execute([$e2eUserId, $e2eRoleId, $now]);
            }
        }
    } finally {
        if (is_string($previousDirectory)) {
            chdir($previousDirectory);
        }
    }

    $keysRoot = $instanceRoot . '/shared/keys';
    if (is_link($instanceRoot) || (!is_dir($instanceRoot) && !mkdir($instanceRoot, 0700, true) && !is_dir($instanceRoot))) {
        throw new RuntimeException('Unable to create isolated instance root');
    }
    if (is_link($keysRoot) || (!is_dir($keysRoot) && !mkdir($keysRoot, 0700, true) && !is_dir($keysRoot))) {
        throw new RuntimeException('Unable to create isolated key directory');
    }
    @chmod($instanceRoot, 0700);
    @chmod($keysRoot, 0700);
    $rsaDir = $keysRoot;
    if (!is_dir($rsaDir) && !mkdir($rsaDir, 0777, true) && !is_dir($rsaDir)) {
        throw new RuntimeException('Unable to create RSA directory');
    }
    $privatePath = $rsaDir . '/private.pem';
    $publicPath = $rsaDir . '/public.pem';
    if (!is_file($privatePath) || !is_file($publicPath)) {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($key === false || !openssl_pkey_export($key, $privateKey)) {
            throw new RuntimeException('Unable to generate RSA private key');
        }
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || empty($details['key'])) {
            throw new RuntimeException('Unable to generate RSA public key');
        }
        file_put_contents($privatePath, $privateKey);
        file_put_contents($publicPath, $details['key']);
    }

    echo $database . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    if ($databaseCreated && $pdo instanceof PDO) {
        try {
            $pdo->exec("DROP DATABASE IF EXISTS {$quoted}");
        } catch (Throwable) {
        }
    }
    exit(1);
}
