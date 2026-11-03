<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Contract\ReleaseSignatureVerifier;

/** 使用部署配置中的受信任公钥验签，包内文件不能扩展此信任集合。 */
final class OpenSslReleaseSignatureVerifier implements ReleaseSignatureVerifier
{
    private const MAX_PEM_BYTES = 1048576;

    /** @var array<string, resource|\OpenSSLAsymmetricKey> */
    private array $trustedKeys = [];

    /** @param array<string, string>|null $trustedPublicKeys key ID 到 PEM 内容或 PEM 文件路径的映射。 */
    public function __construct(?array $trustedPublicKeys = null)
    {
        if (!extension_loaded('openssl')) {
            throw new \RuntimeException('OpenSSL 扩展不可用，无法验证 Release 签名');
        }

        foreach ($trustedPublicKeys ?? TrustedReleaseKeys::configured() as $keyId => $pemOrPath) {
            if (!is_string($keyId)
                || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $keyId) !== 1
                || !is_string($pemOrPath)
                || trim($pemOrPath) === '') {
                throw new \InvalidArgumentException('受信任 Release 公钥配置不合法');
            }

            $pem = $this->readPem($pemOrPath);
            if (preg_match('/-----BEGIN (?:RSA |EC |ENCRYPTED )?PRIVATE KEY-----/', $pem) === 1) {
                throw new \InvalidArgumentException('Release 验签配置必须使用公钥，不能使用私钥');
            }

            $key = openssl_pkey_get_public($pem);
            if ($key === false) {
                throw new \InvalidArgumentException('受信任 Release 公钥无法解析: ' . $keyId);
            }
            $this->trustedKeys[$keyId] = $key;
        }
    }

    public function verify(string $keyId, string $canonicalPayload, string $signatureBytes): bool
    {
        if (!isset($this->trustedKeys[$keyId]) || $signatureBytes === '') return false;

        $result = openssl_verify(
            $canonicalPayload,
            $signatureBytes,
            $this->trustedKeys[$keyId],
            OPENSSL_ALGO_SHA256
        );
        if ($result === -1) throw new \RuntimeException('OpenSSL Release 验签执行失败');
        return $result === 1;
    }

    private function readPem(string $pemOrPath): string
    {
        if (strpos($pemOrPath, '-----BEGIN ') !== false) {
            if (strlen($pemOrPath) > self::MAX_PEM_BYTES) {
                throw new \InvalidArgumentException('受信任 Release 公钥内容过大');
            }
            return $pemOrPath;
        }

        if (strpos($pemOrPath, "\0") !== false
            || preg_match('#^[A-Za-z][A-Za-z0-9+.-]*://#', $pemOrPath) === 1
            || !is_file($pemOrPath)
            || !is_readable($pemOrPath)) {
            throw new \InvalidArgumentException('受信任 Release 公钥文件不可读');
        }
        $size = filesize($pemOrPath);
        if ($size === false || $size < 1 || $size > self::MAX_PEM_BYTES) {
            throw new \InvalidArgumentException('受信任 Release 公钥文件大小不合法');
        }
        $pem = file_get_contents($pemOrPath);
        if ($pem === false || strlen($pem) !== $size) {
            throw new \RuntimeException('读取受信任 Release 公钥失败');
        }
        return $pem;
    }
}
