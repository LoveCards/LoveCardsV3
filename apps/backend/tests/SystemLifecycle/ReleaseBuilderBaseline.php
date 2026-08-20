<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Operations\SystemLifecycle\Application\ReleaseManifestVerifier;
use app\modules\Operations\SystemLifecycle\Domain\HandlerCatalogHasher;
use app\modules\Operations\SystemLifecycle\Infrastructure\OpenSslReleaseSignatureVerifier;
use app\modules\Operations\SystemLifecycle\Infrastructure\ReleaseArchiveLimits;
use app\modules\Operations\SystemLifecycle\Infrastructure\SecureReleaseImporter;
use app\modules\Operations\SystemLifecycle\Infrastructure\SignedReleaseBuilder;
use app\modules\Platform\Eventing\Public\HandlerRegistry;
use app\shared\SystemPaths\SystemPaths;

$opensslConfig = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'local-tools' . DIRECTORY_SEPARATOR . 'php82'
    . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
if (is_file($opensslConfig)) putenv('OPENSSL_CONF=' . $opensslConfig);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lc-release-builder-' . bin2hex(random_bytes(6));
$source = $tmp . DIRECTORY_SEPARATOR . 'source';
$output = $tmp . DIRECTORY_SEPARATOR . 'artifacts' . DIRECTORY_SEPARATOR . 'release.zip';
$keyPath = $tmp . DIRECTORY_SEPARATOR . 'signing.pem';
$cleanup = static function (string $directory) use (&$cleanup): void {
    if (!is_dir($directory) || is_link($directory)) { if (is_file($directory) || is_link($directory)) @unlink($directory); return; }
    foreach (scandir($directory) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (is_dir($path) && !is_link($path)) $cleanup($path); else @unlink($path);
    }
    @rmdir($directory);
};
$write = static function (string $path, string $contents): void {
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('fixture directory failed');
    if (file_put_contents($path, $contents) === false) throw new RuntimeException('fixture write failed');
};
$throws = static function (callable $callback): void {
    try { $callback(); } catch (Throwable) { return; }
    throw new RuntimeException('expected exception');
};

try {
    $write($source . '/bootstrap/ReleaseBootstrap.php', "<?php final class ReleaseBootstrapFixture {}\n");
    $write($source . '/public/index.php', "<?php echo 'ok';\n");
    $write($source . '/think', "#!/usr/bin/env php\n<?php echo 'cli';\n");
    $write($source . '/vendor/autoload.php', "<?php return true;\n");
    $write($source . '/config/app.php', "<?php return [];\n");
    $migration = "-- @lc-phase expand\nSELECT 1;\n";
    $write($source . '/database/migrations/20260819000003.sql', $migration);
    $write($source . '/app/example.php', "<?php return 'release';\n");
    $write($source . '/.env', "DB_PASSWORD=must-not-ship\n");
    $write($source . '/config/rsa/private.pem', "must-not-ship\n");
    $write($source . '/public/storage/private.txt', "must-not-ship\n");
    $write($source . '/runtime/log/app.log', "must-not-ship\n");

    $keys = openssl_pkey_new(['config' => $opensslConfig, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    if ($keys === false || !openssl_pkey_export($keys, $privatePem, null, ['config' => $opensslConfig])) {
        throw new RuntimeException('fixture key generation failed');
    }
    $details = openssl_pkey_get_details($keys);
    $publicPem = (string) ($details['key'] ?? '');
    $write($keyPath, $privatePem);

    $registry = new HandlerRegistry();
    $limits = new ReleaseArchiveLimits(8 * 1024 * 1024, 100, 2 * 1024 * 1024, 4 * 1024 * 1024, 1024 * 1024);
    $builder = new SignedReleaseBuilder($registry, $limits);
    $result = $builder->build(
        $source,
        $output,
        $keyPath,
        'test-key',
        'release-builder-001',
        '1.0.0',
        'build-001',
        1,
        '0',
        '20260819000003',
        'stable',
        '8.1',
        '5.7',
        [],
        ['redis', 'worker'],
        new DateTimeImmutable('2026-08-20T00:00:00Z'),
        new DateTimeImmutable('2026-08-21T00:00:00Z')
    );
    if (!is_file($output) || $result['release_id'] !== 'release-builder-001' || $result['file_count'] < 6) {
        throw new RuntimeException('builder did not produce expected artifact');
    }

    $zip = new ZipArchive();
    if ($zip->open($output, ZipArchive::RDONLY) !== true) throw new RuntimeException('cannot open built release');
    try {
        foreach (['.env', 'config/rsa/private.pem', 'public/storage/private.txt', 'runtime/log/app.log'] as $forbidden) {
            if ($zip->locateName($forbidden) !== false) throw new RuntimeException('runtime secret shipped: ' . $forbidden);
        }
        $manifestJson = $zip->getFromName('release.json');
        $catalogJson = $zip->getFromName('handler-catalog.json');
        if (!is_string($manifestJson) || !is_string($catalogJson)) throw new RuntimeException('release metadata missing');
        $manifest = json_decode($manifestJson, true, 128, JSON_THROW_ON_ERROR);
        $catalog = json_decode($catalogJson, true, 128, JSON_THROW_ON_ERROR);
        if (($manifest['handler_catalog_hash'] ?? '') !== HandlerCatalogHasher::hashArray($catalog)) {
            throw new RuntimeException('catalog file hash contract mismatch');
        }
        if ($registry->catalogHash() !== HandlerCatalogHasher::hashArray($catalog)) {
            throw new RuntimeException('runtime and release catalog hash algorithms diverge');
        }
        if (($manifest['migration_checksums']['20260819000003'] ?? '') !== hash('sha256', $migration)) {
            throw new RuntimeException('migration checksum mismatch');
        }
    } finally {
        $zip->close();
    }

    $paths = new SystemPaths($tmp . DIRECTORY_SEPARATOR . 'instance', $tmp . DIRECTORY_SEPARATOR . 'active');
    $importer = new SecureReleaseImporter(
        $paths,
        new ReleaseManifestVerifier(new OpenSslReleaseSignatureVerifier(['test-key' => $publicPem]), 0),
        $limits
    );
    $verified = $importer->import($output, 0, new DateTimeImmutable('2026-08-20T01:00:00Z'), $result['checksum']);
    if ($verified->manifest()->releaseId() !== 'release-builder-001'
        || !is_file($paths->releaseRoot('release-builder-001') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php')) {
        throw new RuntimeException('built release cannot pass the real importer');
    }

    $throws(static fn() => $builder->build(
        $source,
        $source . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'release.zip',
        $keyPath,
        'test-key', 'bad-output', '1.0.1', 'bad', 2
    ));
    $sourceKey = $source . DIRECTORY_SEPARATOR . 'private.pem';
    $write($sourceKey, $privatePem);
    $throws(static fn() => $builder->build(
        $source,
        $tmp . DIRECTORY_SEPARATOR . 'bad-key.zip',
        $sourceKey,
        'test-key', 'bad-key', '1.0.1', 'bad', 2
    ));

    fwrite(STDOUT, "PASS signed builder produces an importer-compatible artifact without runtime secrets\n");
    fwrite(STDOUT, "PASS handler catalog uses one canonical hash at build, import and runtime\n");
    fwrite(STDOUT, "PASS builder rejects in-tree outputs and signing keys\n3 Release builder tests passed.\n");
} finally {
    $cleanup($tmp);
}
