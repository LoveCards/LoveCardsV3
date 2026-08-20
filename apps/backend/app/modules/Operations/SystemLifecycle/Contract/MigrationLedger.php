<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

use app\modules\Operations\SystemLifecycle\Domain\MigrationDescriptor;
use app\modules\Operations\SystemLifecycle\Domain\MigrationProgress;

/**
 * 持久化实现必须让 initialize 原子幂等，并让 save 使用 revision 做 Compare-And-Swap。
 */
interface MigrationLedger
{
    /** @return MigrationProgress[] */
    public function all(): array;
    public function find(string $migrationId): ?MigrationProgress;
    public function initialize(MigrationDescriptor $descriptor): MigrationProgress;
    public function save(MigrationProgress $progress, int $expectedRevision): MigrationProgress;
}
