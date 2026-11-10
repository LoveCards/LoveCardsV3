<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

use app\modules\Operations\SystemLifecycle\Domain\ReleaseManifest;

interface ReleaseCompatibility
{
    public function verifyEnvironment(ReleaseManifest $manifest): void;
    public function verifyStagedRelease(ReleaseManifest $manifest, string $releaseRoot): void;
    public function verifyRuntimeHandlerCatalog(ReleaseManifest $manifest): void;
    public function requiresContract(ReleaseManifest $manifest, string $releaseRoot): bool;
    public function verifyPreContractMigrationsComplete(ReleaseManifest $manifest): void;
    public function verifyMigrationsComplete(ReleaseManifest $manifest): void;
    public function verifyEventConsumptionReady(): void;
    public function verifyEventConsumptionOperational(string $activatedAt, string $releaseId): void;
}
