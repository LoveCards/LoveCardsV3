<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Application;

use app\modules\Operations\SystemLifecycle\Contract\Migration;
use app\modules\Operations\SystemLifecycle\Contract\MigrationLedger;
use app\modules\Operations\SystemLifecycle\Domain\MigrationPlan;
use app\modules\Operations\SystemLifecycle\Domain\MigrationPlanStep;
use app\modules\Operations\SystemLifecycle\Domain\MigrationProgress;
use app\modules\Operations\SystemLifecycle\Exception\DirtyMigrationException;
use app\modules\Operations\SystemLifecycle\Exception\SchemaDriftException;

final class MigrationPlanner
{
    private MigrationLedger $ledger;

    public function __construct(MigrationLedger $ledger) { $this->ledger = $ledger; }

    /** @param Migration[] $migrations */
    public function plan(array $migrations): MigrationPlan
    {
        $catalog = [];
        foreach ($migrations as $migration) {
            if (!$migration instanceof Migration) throw new \InvalidArgumentException('Migration Catalog 包含无效对象');
            $descriptor = $migration->descriptor();
            if (isset($catalog[$descriptor->id()])) throw new \InvalidArgumentException('Migration ID 重复: ' . $descriptor->id());
            $catalog[$descriptor->id()] = $descriptor;
        }
        ksort($catalog, SORT_STRING);

        $records = [];
        foreach ($this->ledger->all() as $record) {
            if (!$record instanceof MigrationProgress) throw new \UnexpectedValueException('MigrationLedger 返回了无效记录');
            if (isset($records[$record->id()])) throw new SchemaDriftException('Migration Ledger 存在重复记录: ' . $record->id());
            if (!isset($catalog[$record->id()])) throw new SchemaDriftException('Migration Ledger 存在代码中未知的记录: ' . $record->id());
            $records[$record->id()] = $record;
        }

        $seenGap = false;
        $steps = [];
        foreach ($catalog as $id => $descriptor) {
            $record = $records[$id] ?? null;
            if ($record === null) {
                $seenGap = true;
            } else {
                if ($seenGap) throw new SchemaDriftException('Migration Ledger 跳过了更早的 Migration: ' . $id);
                if (!hash_equals($descriptor->checksum(), $record->checksum())) throw DirtyMigrationException::forId((string) $id);
            }
            $steps[] = new MigrationPlanStep($descriptor, $record);
        }
        return new MigrationPlan($steps);
    }

    /** 仅供已导入完整 schema.sql 的首次安装调用；可在写账本中断后重试。 */
    public function recordInstalledTarget(array $migrations): void
    {
        foreach ($this->plan($migrations)->steps() as $step) {
            $progress = $step->progress();
            if ($progress?->isComplete()) continue;
            if ($progress !== null && ($progress->status() !== 'pending' || $progress->attempts() !== 0)) {
                throw new SchemaDriftException('首次安装账本包含未完成的历史执行状态: ' . $step->descriptor()->id());
            }
            $progress ??= $this->ledger->initialize($step->descriptor());
            if (!hash_equals($step->descriptor()->checksum(), $progress->checksum())) {
                throw DirtyMigrationException::forId($step->descriptor()->id());
            }
            $this->ledger->save($progress->installedTarget(), $progress->revision());
        }
    }
}
