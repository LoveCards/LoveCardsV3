<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Operations\SystemLifecycle\Application\ReleaseManifestVerifier;
use app\modules\Operations\SystemLifecycle\Domain\ReleaseManifest;
use app\modules\Operations\SystemLifecycle\Exception\ReleaseManifestException;
use app\modules\Operations\SystemLifecycle\Exception\ReleaseVerificationException;
use app\modules\Operations\SystemLifecycle\Infrastructure\OpenSslReleaseSignatureVerifier;
use app\modules\Operations\SystemLifecycle\Infrastructure\ReleaseArchiveLimits;
use app\modules\Operations\SystemLifecycle\Infrastructure\SecureReleaseImporter;
use app\shared\SystemPaths\SystemPaths;

$opensslConfig = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'local-tools' . DIRECTORY_SEPARATOR . 'php82' . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
if (is_file($opensslConfig)) putenv('OPENSSL_CONF=' . $opensslConfig);

$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void { $tests[$name] = $callback; };
$assert = static function (bool $condition, string $message = 'assertion failed'): void {
    if (!$condition) throw new RuntimeException($message);
};
$assertThrows = static function (string $class, callable $callback) use ($assert): void {
    try { $callback(); } catch (Throwable $exception) {
        $assert($exception instanceof $class, 'unexpected exception: ' . get_class($exception) . ': ' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('expected exception: ' . $class);
};

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lc-release-security-' . bin2hex(random_bytes(6));
if (!mkdir($tmp, 0700, true)) throw new RuntimeException('failed to create test root');
$cleanup = static function (string $directory) use (&$cleanup): void {
    if (!is_dir($directory) || is_link($directory)) { if (is_file($directory)) @unlink($directory); return; }
    foreach (scandir($directory) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (is_dir($path) && !is_link($path)) $cleanup($path); else @unlink($path);
    }
    @rmdir($directory);
};

$keys = openssl_pkey_new(['config' => $opensslConfig, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
if ($keys === false) throw new RuntimeException('failed to generate RSA fixture');
if (!openssl_pkey_export($keys, $privatePem, null, ['config' => $opensslConfig])) throw new RuntimeException('failed to export RSA fixture');
$details = openssl_pkey_get_details($keys);
$publicPem = (string) ($details['key'] ?? '');
if ($publicPem === '') throw new RuntimeException('failed to export public RSA fixture');

$makeManifest = static function (string $signature, string $migrationHash, string $fileHash): array {
    return [
        'schema_version' => 1,
        'release_id' => 'release-test-001',
        'version' => '1.2.3',
        'build' => 'build-test-001',
        'channel' => 'stable',
        'sequence' => 1,
        'issued_at' => '2026-08-19T00:00:00Z',
        'expires_at' => '2026-08-20T00:00:00Z',
        'minimum_php' => '8.1',
        'minimum_mysql' => '5.7',
        'required_extensions' => [],
        'optional_facilities' => [],
        'source_schema_range' => ['min' => '0', 'max' => '20260819000003'],
        'target_schema' => '20260819000003',
        'migration_checksums' => ['20260819000003' => $migrationHash],
        'handler_catalog_hash' => str_repeat('a', 64),
        'file_hashes' => [
            'app.txt' => $fileHash,
            'database/migrations/20260819000003.sql' => $migrationHash,
        ],
        'signature_key_id' => 'test-key',
        'signature' => $signature,
    ];
};

$makeZip = static function (string $path, array $manifest, array $extra = []): void {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('failed to open fixture ZIP');
    $zip->addFromString('release.json', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $zip->addFromString('app.txt', "release payload\n");
    $zip->addFromString('database/migrations/20260819000003.sql', "-- migration fixture\n");
    foreach ($extra as $name => $value) $zip->addFromString($name, (string) $value);
    if (!$zip->close()) throw new RuntimeException('failed to close fixture ZIP');
};

$migrationHash = hash('sha256', "-- migration fixture\n");
$fileHash = hash('sha256', "release payload\n");
$unsigned = ReleaseManifest::fromArray($makeManifest(base64_encode('placeholder'), $migrationHash, $fileHash));
if (!openssl_sign($unsigned->canonicalPayload(), $signatureBytes, $privatePem, OPENSSL_ALGO_SHA256)) throw new RuntimeException('failed to sign fixture');
$manifestData = $makeManifest(base64_encode($signatureBytes), $migrationHash, $fileHash);
$manifest = ReleaseManifest::fromArray($manifestData);
$zipPath = $tmp . DIRECTORY_SEPARATOR . 'release.zip';
$makeZip($zipPath, $manifestData);
$paths = new SystemPaths($tmp . DIRECTORY_SEPARATOR . 'instance', $tmp . DIRECTORY_SEPARATOR . 'active-code');
$verifier = new ReleaseManifestVerifier(new OpenSslReleaseSignatureVerifier(['test-key' => $publicPem]), 3600);
$limits = new ReleaseArchiveLimits(8 * 1024 * 1024, 100, 2 * 1024 * 1024, 4 * 1024 * 1024, 1024 * 1024);

$test('valid signed ZIP is verified, hash-checked and staged immutably', static function () use ($zipPath, $paths, $verifier, $limits, $manifest, $assert): void {
    $importer = new SecureReleaseImporter($paths, $verifier, $limits);
    $inspected = $importer->inspect($zipPath);
    $assert($inspected->documentChecksum() === $manifest->documentChecksum(), 'inspect checksum mismatch');
    $verified = $importer->import($zipPath, 0, new DateTimeImmutable('2026-08-19T01:00:00Z'));
    $assert($verified->manifest()->releaseId() === 'release-test-001', 'wrong release ID');
    $destination = $paths->releaseRoot('release-test-001');
    $assert(is_file($destination . DIRECTORY_SEPARATOR . 'app.txt'), 'staged file missing');
    $second = $importer->import($zipPath, 0, new DateTimeImmutable('2026-08-19T01:00:00Z'));
    $assert($second->manifest()->documentChecksum() === $verified->manifest()->documentChecksum(), 'idempotent import mismatch');
    if (DIRECTORY_SEPARATOR !== '\\') $assert((fileperms($destination) & 0222) === 0, 'release root remains writable');
});

$test('path traversal is rejected before extraction', static function () use ($tmp, $manifestData, $makeZip, $paths, $verifier, $limits, $assertThrows): void {
    $path = $tmp . DIRECTORY_SEPARATOR . 'traversal.zip';
    $makeZip($path, $manifestData, ['../outside.txt' => 'blocked']);
    $importer = new SecureReleaseImporter($paths, $verifier, $limits);
    $assertThrows(RuntimeException::class, static fn() => $importer->inspect($path));
    if (is_file($tmp . DIRECTORY_SEPARATOR . 'outside.txt')) throw new RuntimeException('traversal escaped staging');
});

$test('invalid signature is rejected and does not create a Release directory', static function () use ($tmp, $manifestData, $makeZip, $paths, $verifier, $limits, $assertThrows): void {
    $invalid = $manifestData;
    $invalid['release_id'] = 'release-invalid-001';
    $invalid['signature'] = base64_encode('invalid-signature');
    $path = $tmp . DIRECTORY_SEPARATOR . 'invalid-signature.zip';
    $makeZip($path, $invalid);
    $importer = new SecureReleaseImporter($paths, $verifier, $limits);
    $assertThrows(ReleaseVerificationException::class, static fn() => $importer->import($path, 0, new DateTimeImmutable('2026-08-19T01:00:00Z')));
    if (is_dir($paths->releaseRoot('release-invalid-001'))) throw new RuntimeException('invalid signature was staged');
});

$test('manifest file hash mismatch is rejected', static function () use ($tmp, $manifestData, $makeZip, $paths, $verifier, $limits, $privatePem, $assertThrows): void {
    $invalid = $manifestData;
    $invalid['file_hashes']['app.txt'] = str_repeat('b', 64);
    $unsignedInvalid = ReleaseManifest::fromArray($invalid);
    if (!openssl_sign($unsignedInvalid->canonicalPayload(), $invalidSignature, $privatePem, OPENSSL_ALGO_SHA256)) throw new RuntimeException('failed to sign invalid fixture');
    $invalid['signature'] = base64_encode($invalidSignature);
    $path = $tmp . DIRECTORY_SEPARATOR . 'invalid-hash.zip';
    $makeZip($path, $invalid);
    $importer = new SecureReleaseImporter($paths, $verifier, $limits);
    $assertThrows(ReleaseManifestException::class, static fn() => $importer->import($path, 0, new DateTimeImmutable('2026-08-19T01:00:00Z')));
});

$failures = 0;
try {
    foreach ($tests as $name => $callback) {
        try { $callback(); fwrite(STDOUT, "PASS {$name}\n"); }
        catch (Throwable $exception) { $failures++; fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n"); }
    }
} finally {
    $cleanup($tmp);
}
if ($failures > 0) exit(1);
fwrite(STDOUT, count($tests) . " Release security tests passed.\n");
