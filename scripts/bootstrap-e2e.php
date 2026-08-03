<?php

declare(strict_types=1);

$mode = $argv[1] ?? 'create';
$database = $argv[2] ?? '';

if (!preg_match('/^lovecards_e2e_[a-z0-9_]+$/', $database)) {
    fwrite(STDERR, "Refusing E2E database name: {$database}\n");
    exit(1);
}

$host = getenv('LOVECARDS_E2E_DB_HOST') ?: '127.0.0.1';
$port = getenv('LOVECARDS_E2E_DB_PORT') ?: '3306';
$user = getenv('LOVECARDS_E2E_DB_USER') ?: 'root';
$password = getenv('LOVECARDS_E2E_DB_PASSWORD') ?: '';
$dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
$pdo = null;
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

    $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $databasePdo = new PDO("{$dsn};dbname={$database}", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $sql = file_get_contents(__DIR__ . '/../apps/backend/data.sql');
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('apps/backend/data.sql is missing or empty');
    }
    $databasePdo->exec($sql);

    $rsaDir = __DIR__ . '/../apps/backend/config/rsa';
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
    try {
        $pdo->exec("DROP DATABASE IF EXISTS {$quoted}");
    } catch (Throwable) {
    }
    exit(1);
}
