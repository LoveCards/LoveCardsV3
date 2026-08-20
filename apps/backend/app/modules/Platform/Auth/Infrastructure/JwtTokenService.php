<?php

namespace app\modules\Platform\Auth\Infrastructure;

use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\modules\Platform\Auth\Contract\TokenService;
use app\modules\Platform\Auth\Domain\SessionId;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use think\facade\Cache;
use think\facade\Config;

final class JwtTokenService implements TokenService
{
    private $clock;
    private $jtiFactory;

    public function __construct(
        private SessionRegistry $sessions,
        ?callable $clock = null,
        ?callable $jtiFactory = null
    ) {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->jtiFactory = $jtiFactory ?? static fn(): string => SessionId::generate()->raw();
    }

    public function sign(array $data): string
    {
        $userId = (int) ($data['uid'] ?? 0);
        if ($userId < 1) {
            throw new RuntimeException('Token 缺少有效用户标识');
        }

        unset($data['_new_token'], $data['security_version']);
        $now = $this->now();
        [$tokenExpiresAt, $renewableUntil] = $this->deadlines($now);
        $jti = $this->newJti();
        $session = $this->sessions->start(
            $userId,
            $jti->hash(),
            $tokenExpiresAt,
            $renewableUntil
        );
        $data['security_version'] = $session->securityVersion();

        return Jwt::issue($data, $jti->raw(), $now->getTimestamp(), $tokenExpiresAt->getTimestamp());
    }

    public function verify(string $token): array
    {
        $claims = Jwt::inspect($token);
        $now = $this->now();
        if ($claims['jti'] === '') {
            return $this->migrateLegacy($token, $claims, $now);
        }

        try {
            $jti = SessionId::fromRaw($claims['jti']);
        } catch (\InvalidArgumentException) {
            throw new RuntimeException('token内容无效');
        }

        $data = $claims['data'];
        $userId = (int) ($data['uid'] ?? 0);
        $securityVersion = (int) ($data['security_version'] ?? 0);
        if ($userId < 1 || $securityVersion < 1) {
            throw new RuntimeException('token内容无效');
        }

        $expired = $claims['expired'] || $now->getTimestamp() >= $claims['exp'];
        if (!$expired) {
            $session = $this->sessions->findActive($jti->hash(), $now);
            if ($session === null || !$session->matchesToken($userId, $securityVersion, $claims['exp'])) {
                throw new RuntimeException('token已失效');
            }

            return $data;
        }

        return $this->renew($jti, $data, $claims['exp'], $now);
    }

    public function invalidate(string $token): void
    {
        $claims = Jwt::inspect($token);
        if ($claims['jti'] === '') {
            Cache::delete($this->legacyKey($token));
            return;
        }

        try {
            $jtiHash = SessionId::fromRaw($claims['jti'])->hash();
        } catch (\InvalidArgumentException) {
            throw new RuntimeException('token内容无效');
        }

        $this->sessions->revoke($jtiHash, 'logout', $this->now());
    }

    private function renew(SessionId $oldJti, array $data, int $oldExpiresAt, DateTimeImmutable $now): array
    {
        $newJti = $this->newJti();
        [$tokenExpiresAt, $renewableUntil] = $this->deadlines($now);
        $session = $this->sessions->rotate(
            $oldJti->hash(),
            $newJti->hash(),
            (int) $data['uid'],
            (int) $data['security_version'],
            $oldExpiresAt,
            $tokenExpiresAt,
            $renewableUntil,
            $now
        );
        if ($session === null) {
            throw new RuntimeException('token已失效');
        }

        unset($data['_new_token']);
        $data['security_version'] = $session->securityVersion();
        $newToken = Jwt::issue(
            $data,
            $newJti->raw(),
            $now->getTimestamp(),
            $tokenExpiresAt->getTimestamp()
        );
        $data['_new_token'] = $newToken;

        return $data;
    }

    private function migrateLegacy(string $token, array $claims, DateTimeImmutable $now): array
    {
        $config = Config::get('jwt');
        $cutoff = (int) ($config['legacyBridgeUntil'] ?? 0);
        $renewalWindow = (int) ($config['cacheTime'] ?? 0);
        if (
            $cutoff < 1
            || $now->getTimestamp() >= $cutoff
            || $now->getTimestamp() >= $claims['exp'] + $renewalWindow
            || Cache::pull($this->legacyKey($token)) === null
        ) {
            throw new RuntimeException('token已失效');
        }

        $data = $claims['data'];
        $data['_new_token'] = $this->sign($data);

        return $data;
    }

    /** @return array{DateTimeImmutable, DateTimeImmutable} */
    private function deadlines(DateTimeImmutable $now): array
    {
        $config = Config::get('jwt');
        $lifetime = (int) ($config['exp'] ?? 0);
        $renewalWindow = (int) ($config['cacheTime'] ?? 0);
        if ($lifetime < 1 || $renewalWindow < 0) {
            throw new RuntimeException('JWT 有效期配置无效');
        }

        $tokenExpiresAt = $now->modify('+' . $lifetime . ' seconds');

        return [$tokenExpiresAt, $tokenExpiresAt->modify('+' . $renewalWindow . ' seconds')];
    }

    private function newJti(): SessionId
    {
        return SessionId::fromRaw((string) ($this->jtiFactory)());
    }

    private function now(): DateTimeImmutable
    {
        $now = ($this->clock)();
        if (!$now instanceof DateTimeImmutable) {
            throw new RuntimeException('JWT Clock 必须返回 DateTimeImmutable');
        }

        return $now->setTimezone(new DateTimeZone('UTC'));
    }

    private function legacyKey(string $token): string
    {
        return 'token_' . $token;
    }
}
