<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

use app\modules\Operations\SystemLifecycle\Domain\ReleaseManifest;
use app\modules\Operations\SystemLifecycle\Domain\VerifiedReleaseManifest;

interface ReleasePackageImporter
{
    public function inspect(string $zipPath): ReleaseManifest;

    public function verify(
        string $zipPath,
        int $currentSequence = 0,
        ?\DateTimeImmutable $now = null,
        ?string $expectedPackageChecksum = null
    ): VerifiedReleaseManifest;

    public function import(
        string $zipPath,
        int $currentSequence = 0,
        ?\DateTimeImmutable $now = null,
        ?string $expectedPackageChecksum = null
    ): VerifiedReleaseManifest;
}
