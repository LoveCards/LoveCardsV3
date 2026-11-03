<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Application;

use app\modules\Operations\SystemLifecycle\Contract\ReleaseSignatureVerifier;
use app\modules\Operations\SystemLifecycle\Domain\ReleaseManifest;
use app\modules\Operations\SystemLifecycle\Domain\VerifiedReleaseManifest;
use app\modules\Operations\SystemLifecycle\Exception\ReleaseVerificationException;

final class ReleaseManifestVerifier
{
    private ReleaseSignatureVerifier $signatureVerifier;
    private int $maximumClockSkewSeconds;

    public function __construct(ReleaseSignatureVerifier $signatureVerifier, int $maximumClockSkewSeconds = 300)
    {
        if ($maximumClockSkewSeconds < 0 || $maximumClockSkewSeconds > 3600) {
            throw new \InvalidArgumentException('Manifest 时钟容差不合法');
        }
        $this->signatureVerifier = $signatureVerifier;
        $this->maximumClockSkewSeconds = $maximumClockSkewSeconds;
    }

    public function verify(ReleaseManifest $manifest, int $currentSequence, \DateTimeImmutable $now): VerifiedReleaseManifest
    {
        if ($currentSequence < 0) throw new \InvalidArgumentException('当前 Release Sequence 不能为负数');
        if ($manifest->sequence() <= $currentSequence) {
            throw new ReleaseVerificationException('Release Sequence 未前进，已阻止降级或重复激活');
        }
        $latestAllowedIssueTime = $now->modify('+' . $this->maximumClockSkewSeconds . ' seconds');
        if ($manifest->issuedAt() > $latestAllowedIssueTime) {
            throw new ReleaseVerificationException('Release Manifest 签发时间超出允许时钟偏差');
        }
        if ($manifest->expiresAt() <= $now) throw new ReleaseVerificationException('Release Manifest 已过期');

        try {
            $valid = $this->signatureVerifier->verify(
                $manifest->signatureKeyId(),
                $manifest->canonicalPayload(),
                $manifest->signatureBytes()
            );
        } catch (\Throwable $exception) {
            throw new ReleaseVerificationException('Release Manifest 验签失败', 0, $exception);
        }
        if (!$valid) throw new ReleaseVerificationException('Release Manifest 签名不可信');
        return new VerifiedReleaseManifest($manifest, $now);
    }
}
