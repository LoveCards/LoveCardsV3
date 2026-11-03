<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

use app\modules\Operations\SystemLifecycle\Domain\BackupArtifact;

interface SystemBackupRepository
{
    /** Returns false when an already verified artifact can be reused. */
    public function reserve(string $backupId, ?string $planId, string $type, ?int $actorId): bool;
    public function complete(BackupArtifact $artifact, ?string $planId, ?int $actorId): void;
    public function fail(string $backupId, string $failureCode): void;
    /** @return array<int, array<string,mixed>> */
    public function forPlan(string $planId): array;
    public function markRestored(string $backupId): void;
}
