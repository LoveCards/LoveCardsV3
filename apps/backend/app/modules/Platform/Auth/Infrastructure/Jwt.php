<?php

namespace app\modules\Platform\Auth\Infrastructure;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\Key;
use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\SignatureInvalidException;
use RuntimeException;
use think\facade\Config;
use UnexpectedValueException;
use app\shared\SystemPaths\PersistentConfiguration;

/** JWT 编解码器；会话有效性由 JwtTokenService 与 SessionRegistry 负责。 */
final class Jwt
{
    public static function issue(array $data, string $jti, int $issuedAt, int $expiresAt): string
    {
        $config = Config::get('jwt');
        $privateKeyPath = PersistentConfiguration::resolvePath((string) $config['privateKey'], app()->getRootPath());
        $privateKey = file_get_contents($privateKeyPath);
        if ($privateKey === false) {
            throw new RuntimeException('JWT 私钥不可用');
        }

        return FirebaseJwt::encode([
            'iss' => $config['iss'],
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => $jti,
            'data' => $data,
        ], $privateKey, $config['alg']);
    }

    /**
     * 返回经过签名校验的 Token 内容。过期 Token 仍返回 Claims，供数据库原子续期使用。
     *
     * @return array{data: array, jti: string, iat: int, exp: int, expired: bool}
     */
    public static function inspect(string $token): array
    {
        $config = Config::get('jwt');
        $publicKeyPath = PersistentConfiguration::resolvePath((string) $config['publicKey'], app()->getRootPath());
        $publicKey = file_get_contents($publicKeyPath);
        if ($publicKey === false) {
            throw new RuntimeException('JWT 公钥不可用');
        }

        $expired = false;
        try {
            $payload = FirebaseJwt::decode($token, new Key($publicKey, $config['alg']));
        } catch (ExpiredException) {
            // Firebase JWT 只会在签名和 nbf 校验通过后抛出 ExpiredException。
            $payload = self::payload($token);
            $expired = true;
        } catch (SignatureInvalidException) {
            throw new RuntimeException('签名不正确');
        } catch (BeforeValidException) {
            throw new RuntimeException('token未生效');
        } catch (UnexpectedValueException $exception) {
            throw new RuntimeException('未知错误:' . $exception->getMessage());
        }

        if ((string) ($payload->iss ?? '') !== (string) $config['iss']) {
            throw new RuntimeException('token签发方不正确');
        }

        $data = isset($payload->data) ? (array) $payload->data : [];
        $issuedAt = (int) ($payload->iat ?? 0);
        $expiresAt = (int) ($payload->exp ?? 0);
        if ($issuedAt < 1 || $expiresAt <= $issuedAt || !isset($data['uid'])) {
            throw new RuntimeException('token内容无效');
        }

        return [
            'data' => $data,
            'jti' => (string) ($payload->jti ?? ''),
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'expired' => $expired,
        ];
    }

    public static function verifyRsa($publicKey, $privateKey): bool
    {
        try {
            $config = Config::get('jwt');
            $token = FirebaseJwt::encode(['Test' => true], $privateKey, $config['alg']);

            return (FirebaseJwt::decode($token, new Key($publicKey, $config['alg']))->Test ?? null) === true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function payload(string $token): object
    {
        $segments = explode('.', $token);
        if (count($segments) !== 3) {
            throw new RuntimeException('token内容无效');
        }

        try {
            return FirebaseJwt::jsonDecode(FirebaseJwt::urlsafeB64Decode($segments[1]));
        } catch (\Throwable) {
            throw new RuntimeException('token内容无效');
        }
    }
}
