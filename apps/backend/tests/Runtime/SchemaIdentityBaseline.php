<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Operations\SystemLifecycle\Contract\MigrationLedger;
use app\modules\Operations\SystemLifecycle\Contract\MigrationSource;
use app\modules\Operations\SystemLifecycle\Domain\MigrationDescriptor;
use app\modules\Operations\SystemLifecycle\Domain\MigrationProgress;
use app\modules\Operations\SystemLifecycle\Infrastructure\MigrationCatalog;
use app\modules\Operations\SystemLifecycle\Public\SchemaIdentity;

final class SchemaIdentityFixtureLedger implements MigrationLedger
{
    public array $rows = [];
    public int $allCalls = 0;
    public int $readOnlyCalls = 0;
    public function all(): array { $this->allCalls++; return array_values($this->rows); }
    public function allReadOnly(): array { $this->readOnlyCalls++; return array_values($this->rows); }
    public function find(string $migrationId): ?MigrationProgress { return $this->rows[$migrationId] ?? null; }
    public function initialize(MigrationDescriptor $descriptor): MigrationProgress
    { return $this->rows[$descriptor->id()] ??= MigrationProgress::pending($descriptor); }
    public function save(MigrationProgress $progress, int $expectedRevision): MigrationProgress
    { return $this->rows[$progress->id()] = $progress->withRevision($expectedRevision + 1); }
}

final class CountingSchemaIdentitySource implements MigrationSource
{
    public int $discoverCalls = 0;
    public function __construct(private MigrationSource $source) {}
    public function discover(string $directory): array
    {
        $this->discoverCalls++;
        return $this->source->discover($directory);
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$rejects = static function (callable $fn, string $message): void {
    try { $fn(); } catch (RuntimeException) { return; }
    throw new RuntimeException($message);
};
$directory = sys_get_temp_dir() . '/lc-schema-identity-' . bin2hex(random_bytes(6));
if (!mkdir($directory)) throw new RuntimeException('fixture directory unavailable');
$firstId = '20991230000001';
$secondId = '20991230000002';
$firstPath = $directory . '/' . $firstId . '.sql';
$secondPath = $directory . '/' . $secondId . '.sql';
try {
    file_put_contents($firstPath, "CREATE TABLE fixture_a (id INT);\n");
    $source = new CountingSchemaIdentitySource(new MigrationCatalog());
    $ledger = new SchemaIdentityFixtureLedger();
    $identity = new SchemaIdentity($source, $ledger);
    $first = $source->discover($directory)[0]->descriptor();
    $ledger->rows[$firstId] = MigrationProgress::pending($first)->installedTarget();
    $initial = $identity->forDirectory($directory);
    $assert((bool) preg_match('/^[a-f0-9]{64}$/', $initial), 'Schema identity must be SHA-256');
    $assert($initial === $identity->forDirectory($directory), 'same code and ledger must yield a stable identity');
    $allCalls = $ledger->allCalls;
    $assert($initial === $identity->forDirectoryReadOnly($directory) && $ledger->readOnlyCalls === 1
        && $ledger->allCalls === $allCalls, 'probe must validate code without ledger bootstrap');

    file_put_contents($secondPath, "CREATE TABLE fixture_b (id INT);\n");
    $rejects(fn() => $identity->forDirectory($directory), 'unapplied Worker-local migration accepted');
    $rejects(fn() => $identity->forDirectoryReadOnly($directory), 'FPM code ahead of ledger accepted');
    $second = $source->discover($directory)[1]->descriptor();
    $ledger->rows[$secondId] = MigrationProgress::pending($second);
    $rejects(fn() => $identity->forDirectory($directory), 'pending migration accepted');
    $ledger->rows[$secondId] = $ledger->rows[$secondId]->installedTarget();
    $updated = $identity->forDirectory($directory);
    $assert($updated !== $initial, 'applied new migration must change Schema identity');
    $assert($updated === $identity->forDirectoryReadOnly($directory), 'probe must observe reviewed ledger changes');

    $assert($updated === $identity->primeForDirectory($directory), 'Worker startup must validate and cache its code catalog');
    $discoverCalls = $source->discoverCalls;
    $assert($identity->forDirectoryReadOnly($directory) === $updated
        && $identity->forDirectoryReadOnly($directory) === $updated
        && $source->discoverCalls === $discoverCalls, 'Worker poll must reuse its code catalog snapshot');
    unset($ledger->rows[$secondId]);
    $rejects(fn() => $identity->forDirectoryReadOnly($directory), 'Worker poll missed changed DB ledger');
    $ledger->rows[$secondId] = MigrationProgress::pending($second)->installedTarget();

    file_put_contents($firstPath, "CREATE TABLE fixture_a (id BIGINT);\n");
    $rejects(fn() => $identity->forDirectory($directory), 'modified reviewed migration accepted');
    unlink($firstPath);
    $rejects(fn() => $identity->forDirectory($directory), 'ledger migration unknown to Worker code accepted');
    echo "PASS Worker Schema identity follows reviewed code and complete migration ledger\n";
} finally {
    @unlink($firstPath);
    @unlink($secondPath);
    @rmdir($directory);
}
