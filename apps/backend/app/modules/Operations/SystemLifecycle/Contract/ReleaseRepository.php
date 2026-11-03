<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

use app\modules\Operations\SystemLifecycle\Domain\VerifiedReleaseManifest;

interface ReleaseRepository
{
    public function sequenceFloor(?string $excludingReleaseId = null): int;

    /** @return array<string, mixed>|null */
    public function find(string $releaseId): ?array;

    public function stage(VerifiedReleaseManifest $release): void;
    public function activate(string $releaseId, ?string $previousReleaseId): void;
    public function rollback(string $releaseId, ?string $restoredReleaseId): void;
}
