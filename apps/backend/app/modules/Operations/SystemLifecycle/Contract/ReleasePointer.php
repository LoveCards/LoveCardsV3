<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

interface ReleasePointer
{
    public function activeReleaseId(): ?string;
    public function activate(string $releaseId): void;
    public function restore(string $backupPath, string $expectedChecksum): ?string;

    /** @return array<string, string|null> */
    public function recoveryState(): array;
}
