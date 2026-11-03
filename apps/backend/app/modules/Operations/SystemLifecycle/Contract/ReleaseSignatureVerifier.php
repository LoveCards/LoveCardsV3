<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

/** 生产实现由组合根绑定到受信任公钥仓库，禁止信任 Manifest 自带公钥。 */
interface ReleaseSignatureVerifier
{
    public function verify(string $keyId, string $canonicalPayload, string $signatureBytes): bool;
}
