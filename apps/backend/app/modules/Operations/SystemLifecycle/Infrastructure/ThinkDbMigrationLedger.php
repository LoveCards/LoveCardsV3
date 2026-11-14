<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Contract\MigrationLedger;
use app\modules\Operations\SystemLifecycle\Domain\MigrationDescriptor;
use app\modules\Operations\SystemLifecycle\Domain\MigrationProgress;
use think\facade\Db;

final class ThinkDbMigrationLedger implements MigrationLedger
{
    private bool $bootstrapped = false;

    private function bootstrap(): void
    {
        if ($this->bootstrapped) return;
        // The installer imports schema.sql and seed.sql before the first lifecycle migration.
        // Create only the lifecycle ledger here so the upgrade coordinator can adopt it.
        Db::execute("CREATE TABLE IF NOT EXISTS `system_upgrade_steps` (`migration_id` VARCHAR(128) NOT NULL, `checksum` CHAR(64) NOT NULL, `phase` VARCHAR(16) NOT NULL, `status` VARCHAR(16) NOT NULL, `cursor` VARCHAR(2048) NULL, `revision` INT UNSIGNED NOT NULL DEFAULT 0, `attempts` INT UNSIGNED NOT NULL DEFAULT 0, `failure_code` VARCHAR(128) NULL, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NOT NULL, PRIMARY KEY (`migration_id`), KEY `idx_upgrade_status` (`status`,`updated_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->bootstrapped = true;
    }

    public function all(): array
    {
        $this->bootstrap();
        return $this->allReadOnly();
    }

    public function allReadOnly(): array
    {
        return array_map(fn(array $row): MigrationProgress => MigrationProgress::restore($this->fromRow($row)), Db::table('system_upgrade_steps')->select()->toArray());
    }

    public function find(string $migrationId): ?MigrationProgress
    {
        $this->bootstrap();
        $row = Db::table('system_upgrade_steps')->where('migration_id', $migrationId)->find();
        return $row ? MigrationProgress::restore($this->fromRow($row)) : null;
    }

    public function initialize(MigrationDescriptor $descriptor): MigrationProgress
    {
        $this->bootstrap();
        $progress = MigrationProgress::pending($descriptor);
        if (!Db::table('system_upgrade_steps')->where('migration_id', $descriptor->id())->find()) {
            try { Db::table('system_upgrade_steps')->insert($this->toRow($progress)); } catch (\Throwable $ignored) { /* concurrent initializer won */ }
        }
        return $this->find($descriptor->id()) ?? $progress;
    }

    public function save(MigrationProgress $progress, int $expectedRevision): MigrationProgress
    {
        $this->bootstrap();
        $updated = Db::table('system_upgrade_steps')->where('migration_id', $progress->id())->where('revision', $expectedRevision)->update([
            'checksum' => $progress->checksum(), 'phase' => $progress->phase(), 'status' => $progress->status(),
            'cursor' => $progress->cursor(), 'revision' => $expectedRevision + 1, 'attempts' => $progress->attempts(),
            'failure_code' => $progress->failureCode(), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if ($updated !== 1) throw new \RuntimeException('Migration Ledger CAS 冲突');
        return $progress->withRevision($expectedRevision + 1);
    }

    private function toRow(MigrationProgress $p): array
    { return ['migration_id'=>$p->id(),'checksum'=>$p->checksum(),'phase'=>$p->phase(),'status'=>$p->status(),'cursor'=>$p->cursor(),'revision'=>$p->revision(),'attempts'=>$p->attempts(),'failure_code'=>$p->failureCode(),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]; }
    private function fromRow(array $r): array
    { $r['id'] = $r['migration_id']; return $r; }
}
