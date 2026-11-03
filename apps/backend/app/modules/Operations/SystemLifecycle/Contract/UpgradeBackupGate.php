<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

interface UpgradeBackupGate
{
    /** @return array<int, array<string, mixed>> */
    public function prepare(string $planId, ?int $actorId = null): array;
    public function assertReady(string $planId): void;

    /** @return array<string, mixed> */
    public function pointerBackup(string $planId): array;
}
