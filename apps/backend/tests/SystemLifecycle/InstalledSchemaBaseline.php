<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Operations\SystemLifecycle\Application\MigrationPlanner;
use app\modules\Operations\SystemLifecycle\Contract\MigrationLedger;
use app\modules\Operations\SystemLifecycle\Domain\MigrationDescriptor;
use app\modules\Operations\SystemLifecycle\Domain\MigrationProgress;
use app\modules\Operations\SystemLifecycle\Infrastructure\SqlFileMigration;

final class InstalledSchemaLedger implements MigrationLedger
{
    public array $rows = [];
    public function all(): array { return array_values($this->rows); }
    public function allReadOnly(): array { return $this->all(); }
    public function find(string $id): ?MigrationProgress { return $this->rows[$id] ?? null; }
    public function initialize(MigrationDescriptor $descriptor): MigrationProgress
    {
        return $this->rows[$descriptor->id()] ??= MigrationProgress::pending($descriptor);
    }
    public function save(MigrationProgress $progress, int $expectedRevision): MigrationProgress
    {
        if (($this->rows[$progress->id()] ?? null)?->revision() !== $expectedRevision) {
            throw new RuntimeException('CAS mismatch');
        }
        return $this->rows[$progress->id()] = $progress->withRevision($expectedRevision + 1);
    }
}

$path = tempnam(sys_get_temp_dir(), 'lc-installed-schema-');
if ($path === false) throw new RuntimeException('fixture unavailable');
try {
    file_put_contents($path, "-- @lc-phase expand\nSELECT 1;\n");
    $migration = new SqlFileMigration('20991231000000_installed_fixture', $path, 'fixture');
    $ledger = new InstalledSchemaLedger();
    $planner = new MigrationPlanner($ledger);
    $planner->recordInstalledTarget([$migration]);
    $progress = $ledger->find($migration->descriptor()->id());
    if ($progress?->status() !== 'complete' || $progress->attempts() !== 0
        || $progress->checksum() !== $migration->descriptor()->checksum()) {
        throw new RuntimeException('installed schema did not record a completed checksum without execution');
    }
    $planner->recordInstalledTarget([$migration]);
    if ($ledger->find($migration->descriptor()->id())?->revision() !== 1) {
        throw new RuntimeException('installed schema retry mutated complete ledger');
    }
    file_put_contents($path, "-- @lc-phase expand\nSELECT 2;\n");
    $changed = new SqlFileMigration('20991231000000_installed_fixture', $path, 'fixture');
    try {
        $planner->recordInstalledTarget([$changed]);
        throw new RuntimeException('changed migration checksum accepted');
    } catch (\app\modules\Operations\SystemLifecycle\Exception\DirtyMigrationException) {
        // 修改历史迁移必须失败。
    }
    fwrite(STDOUT, "PASS clean install records reviewed migrations without executing them\n");
} finally {
    @unlink($path);
}
