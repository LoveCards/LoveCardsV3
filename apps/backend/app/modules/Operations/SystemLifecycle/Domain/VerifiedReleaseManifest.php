<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

/** 只有 ReleaseManifestVerifier 应创建本结果。 */
final class VerifiedReleaseManifest
{
    private ReleaseManifest $manifest;
    private \DateTimeImmutable $verifiedAt;

    /** @internal */
    public function __construct(ReleaseManifest $manifest, \DateTimeImmutable $verifiedAt)
    {
        $this->manifest = $manifest;
        $this->verifiedAt = $verifiedAt;
    }

    public function manifest(): ReleaseManifest { return $this->manifest; }
    public function verifiedAt(): \DateTimeImmutable { return $this->verifiedAt; }
}
