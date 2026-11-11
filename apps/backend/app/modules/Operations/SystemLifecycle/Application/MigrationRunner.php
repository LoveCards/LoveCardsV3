<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Application;

use app\modules\Operations\SystemLifecycle\Contract\Migration;
use app\modules\Operations\SystemLifecycle\Contract\MigrationLedger;
use app\modules\Operations\SystemLifecycle\Domain\MigrationPhase;
use app\modules\Operations\SystemLifecycle\Domain\MigrationProgress;
use app\modules\Operations\SystemLifecycle\Domain\MigrationStatus;
use app\modules\Operations\SystemLifecycle\Exception\DirtyMigrationException;

/**
 * 每次调用最多执行一个 Phase 或一个 Backfill 批次。
 * 调用者必须持有 SystemLifecycle 全局 Lease；本骨架不伪造分布式锁。
 */
final class MigrationRunner
{
    private MigrationLedger $ledger;

    public function __construct(MigrationLedger $ledger) { $this->ledger = $ledger; }

    public function runNext(Migration $migration, bool $allowContract = false, bool $retryFailed = false): MigrationProgress
    {
        $descriptor = $migration->descriptor();
        $progress = $this->ledger->find($descriptor->id()) ?? $this->ledger->initialize($descriptor);
        if (!hash_equals($descriptor->checksum(), $progress->checksum())) {
            throw DirtyMigrationException::forId($descriptor->id());
        }
        if ($progress->isComplete()) return $progress;
        if ($progress->status() === MigrationStatus::RUNNING) {
            throw new \LogicException('迁移上次执行未确认结束；必须核实 SQL/子进程并受控处置，禁止自动重放');
        }
        // A failed migration is a deliberate operator stop. Retrying it from
        // the scheduler would create an unbounded failure loop and can repeat
        // non-idempotent expand/backfill work. Require an explicit reset or
        // retry command to transition FAILED back to PENDING.
        if ($progress->status() === MigrationStatus::FAILED) {
            if ($retryFailed) {
                $progress = $this->ledger->save($progress->retry(), $progress->revision());
            } else {
                return $progress;
            }
        }
        if ($progress->phase() === MigrationPhase::CONTRACT && !$allowContract) {
            if ($progress->status() === MigrationStatus::WAITING) return $progress;
            return $this->ledger->save($progress->waitForContract(), $progress->revision());
        }

        $running = $this->ledger->save($progress->started(), $progress->revision());
        try {
            $next = $this->executePhase($migration, $running, $allowContract);
            return $this->ledger->save($next, $running->revision());
        } catch (\Throwable $exception) {
            try {
                $this->ledger->save($running->failed(substr(get_class($exception), 0, 128)), $running->revision());
            } catch (\Throwable $ignored) {
                // 原始 Migration 异常优先返回；账本 CAS 冲突由上层诊断处理。
            }
            throw $exception;
        }
    }

    private function executePhase(Migration $migration, MigrationProgress $progress, bool $allowContract): MigrationProgress
    {
        switch ($progress->phase()) {
            case MigrationPhase::PREFLIGHT:
                $migration->preflight();
                return $progress->advance(MigrationPhase::EXPAND);
            case MigrationPhase::EXPAND:
                $migration->expand();
                return $progress->advance(MigrationPhase::BACKFILL);
            case MigrationPhase::BACKFILL:
                $batch = $migration->backfill($progress->cursor());
                if ($batch->isComplete()) return $progress->advance(MigrationPhase::VERIFY);
                if ($batch->nextCursor() === $progress->cursor()) {
                    throw new \RuntimeException('Backfill 未推进游标');
                }
                return $progress->advance(MigrationPhase::BACKFILL, $batch->nextCursor());
            case MigrationPhase::VERIFY:
                $migration->verify();
                if (!$migration->descriptor()->hasContract()) return $progress->completed();
                if (!$allowContract) return $progress->waitForContract();
                return $progress->advance(MigrationPhase::CONTRACT);
            case MigrationPhase::CONTRACT:
                if (!$allowContract) throw new \LogicException('Contract Migration 尚未获准执行');
                $migration->contract();
                return $progress->completed();
            default:
                throw new \LogicException('Migration Phase 无法执行: ' . $progress->phase());
        }
    }
}
