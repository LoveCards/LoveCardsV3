<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Operations\SystemLifecycle\Application\ReleaseCompatibilityValidator;
use app\modules\Operations\SystemLifecycle\Contract\Migration;
use app\modules\Operations\SystemLifecycle\Contract\MigrationLedger;
use app\modules\Operations\SystemLifecycle\Contract\MigrationSource;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseEnvironment;
use app\modules\Operations\SystemLifecycle\Domain\BackfillBatch;
use app\modules\Operations\SystemLifecycle\Domain\MigrationDescriptor;
use app\modules\Operations\SystemLifecycle\Domain\MigrationProgress;
use app\modules\Operations\SystemLifecycle\Domain\ReleaseManifest;
use app\modules\Operations\SystemLifecycle\Exception\ReleaseVerificationException;
use app\modules\Operations\SystemLifecycle\Domain\HandlerCatalogHasher;

final class ReleaseCompatibilityFakeMigration implements Migration
{
    private MigrationDescriptor $descriptor;
    public function __construct()
    {
        $this->descriptor = MigrationDescriptor::fromSource('20260819000003', 'compatibility fixture', "-- compatibility fixture\n");
    }
    public function descriptor(): MigrationDescriptor { return $this->descriptor; }
    public function preflight(): void {}
    public function expand(): void {}
    public function backfill(?string $cursor): BackfillBatch { return BackfillBatch::complete(); }
    public function verify(): void {}
    public function contract(): void {}
}

final class ReleaseCompatibilityFakeSource implements MigrationSource
{
    public function discover(string $directory): array { return [new ReleaseCompatibilityFakeMigration()]; }
}

final class ReleaseCompatibilityFakeLedger implements MigrationLedger
{
    public function all(): array { return []; }
    public function find(string $migrationId): ?MigrationProgress { return null; }
    public function initialize(MigrationDescriptor $descriptor): MigrationProgress { return MigrationProgress::pending($descriptor); }
    public function save(MigrationProgress $progress, int $expectedRevision): MigrationProgress { return $progress->withRevision($expectedRevision + 1); }
}

final class ReleaseCompatibilityFakeEnvironment implements ReleaseEnvironment
{
    public function __construct(private string $handlerHash) {}
    public function phpVersion(): string { return PHP_VERSION; }
    public function mysqlVersion(): string { return '8.0.36'; }
    public function hasExtension(string $extension): bool { return true; }
    public function handlerCatalogHash(): string { return $this->handlerHash; }
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lc-compatibility-' . bin2hex(random_bytes(6));
mkdir($tmp . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations', 0700, true);
$migrationSource = "-- compatibility fixture\n";
file_put_contents($tmp . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '20260819000003.sql', $migrationSource);
$catalogPath = $tmp . DIRECTORY_SEPARATOR . 'handler-catalog.json';
$catalog = [['event_type' => 'message.sent', 'event_version' => 1, 'handler_key' => 'notifications.message-sent']];
file_put_contents($catalogPath, json_encode($catalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
$catalogHash = HandlerCatalogHasher::hashFile($catalogPath);
$migrationHash = hash('sha256', $migrationSource);
$manifest = ReleaseManifest::fromArray([
    'schema_version' => 1, 'release_id' => 'compatibility-release', 'version' => '1.0.0', 'build' => 'compatibility-build', 'channel' => 'stable', 'sequence' => 1,
    'issued_at' => '2026-08-19T00:00:00Z', 'expires_at' => '2026-08-20T00:00:00Z', 'minimum_php' => '8.1', 'minimum_mysql' => '5.7',
    'required_extensions' => [], 'optional_facilities' => [], 'source_schema_range' => ['min' => '0', 'max' => '20260819000003'], 'target_schema' => '20260819000003',
    'migration_checksums' => ['20260819000003' => $migrationHash], 'handler_catalog_hash' => $catalogHash,
    'file_hashes' => ['handler-catalog.json' => hash_file('sha256', $catalogPath)], 'signature_key_id' => 'test', 'signature' => base64_encode('signature'),
]);
file_put_contents($tmp . DIRECTORY_SEPARATOR . 'release.json', json_encode($manifest->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
$validator = new ReleaseCompatibilityValidator(new ReleaseCompatibilityFakeSource(), new ReleaseCompatibilityFakeLedger(), new ReleaseCompatibilityFakeEnvironment($catalogHash));
$failures = 0;
try {
    @unlink($catalogPath);
    try { $validator->verifyStagedRelease($manifest, $tmp); throw new RuntimeException('missing handler catalog accepted'); }
    catch (ReleaseVerificationException $exception) {}
    file_put_contents($catalogPath, json_encode($catalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $validator->verifyStagedRelease($manifest, $tmp);
    file_put_contents($catalogPath, json_encode([['handler_key' => 'tampered']], JSON_THROW_ON_ERROR));
    try { $validator->verifyStagedRelease($manifest, $tmp); throw new RuntimeException('tampered handler catalog accepted'); }
    catch (ReleaseVerificationException $exception) {}
    fwrite(STDOUT, "PASS handler catalog is fail-closed before staging and checked at runtime\n1 Release compatibility test passed.\n");
} catch (Throwable $exception) {
    $failures++;
    fwrite(STDERR, "FAIL Release compatibility: {$exception->getMessage()}\n");
} finally {
    $cleanup = static function (string $directory) use (&$cleanup): void {
        if (!is_dir($directory) || is_link($directory)) { if (is_file($directory)) @unlink($directory); return; }
        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $directory . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path) && !is_link($path)) $cleanup($path); else @unlink($path);
        }
        @rmdir($directory);
    };
    $cleanup($tmp);
}
if ($failures > 0) exit(1);
