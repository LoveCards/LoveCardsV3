<?php

declare(strict_types=1);

namespace {
    $jwtSessionAuthBase = dirname(__DIR__, 2) . '/app/modules/Platform/Auth/';
    require $jwtSessionAuthBase . 'Contract/TokenService.php';
    require $jwtSessionAuthBase . 'Domain/SessionId.php';
    require $jwtSessionAuthBase . 'Domain/SessionRecord.php';
    require $jwtSessionAuthBase . 'Contract/SessionRegistry.php';
}

namespace Tests\Auth\SessionLifecycle {
    use app\modules\Platform\Auth\Contract\SessionRegistry;
    use app\modules\Platform\Auth\Domain\SessionRecord;
    use DateTimeImmutable;

    final class App
    {
        public function __construct(private string $rootPath)
        {
        }

        public function getRootPath(): string
        {
            return $this->rootPath;
        }
    }

    final class InMemorySessionRegistry implements SessionRegistry
    {
        /** @var array<string, SessionRecord> */
        public array $sessions = [];
        public array $revocations = [];
        public array $rotations = [];
        public array $securityVersions = [];
        private int $nextId = 1;

        public function listForUser(int $userId, bool $includeRevoked = true): array
        {
            $rows = [];
            foreach ($this->sessions as $record) {
                if ($record->userId() !== $userId || (!$includeRevoked && $record->revokedAt() !== null)) {
                    continue;
                }
                $rows[] = [
                    'id' => $record->id(),
                    'user_id' => $record->userId(),
                    'token_expires_at' => $record->tokenExpiresAt()->format('Y-m-d H:i:s'),
                    'renewable_until' => $record->renewableUntil()->format('Y-m-d H:i:s'),
                    'revoked_at' => $record->revokedAt()?->format('Y-m-d H:i:s'),
                    'revoked_reason' => $record->revokedReason(),
                ];
            }

            return $rows;
        }

        public function revokeById(int $userId, int $sessionId, string $reason, DateTimeImmutable $at): bool
        {
            foreach ($this->sessions as $jtiHash => $record) {
                if ($record->id() === $sessionId && $record->userId() === $userId) {
                    return $this->revoke($jtiHash, $reason, $at);
                }
            }

            return false;
        }

        public function start(
            int $userId,
            string $jtiHash,
            DateTimeImmutable $tokenExpiresAt,
            DateTimeImmutable $renewableUntil
        ): SessionRecord {
            $record = new SessionRecord(
                $this->nextId++,
                $jtiHash,
                $userId,
                $tokenExpiresAt,
                $renewableUntil,
                $this->securityVersions[$userId] ?? 1
            );
            $this->sessions[$jtiHash] = $record;

            return $record;
        }

        public function findActive(string $jtiHash, DateTimeImmutable $at): ?SessionRecord
        {
            $record = $this->sessions[$jtiHash] ?? null;
            if ($record === null || !$record->isActiveAt($at)) {
                return null;
            }
            if (($this->securityVersions[$record->userId()] ?? 1) !== $record->securityVersion()) {
                return null;
            }

            return $record;
        }

        public function rotate(
            string $oldJtiHash,
            string $newJtiHash,
            int $userId,
            int $expectedSecurityVersion,
            int $expectedTokenExpiresAt,
            DateTimeImmutable $tokenExpiresAt,
            DateTimeImmutable $renewableUntil,
            DateTimeImmutable $at
        ): ?SessionRecord {
            $old = $this->sessions[$oldJtiHash] ?? null;
            if (
                $old === null
                || !$old->isRenewableAt($at)
                || $old->userId() !== $userId
                || $old->securityVersion() !== $expectedSecurityVersion
                || $old->tokenExpiresAt()->getTimestamp() !== $expectedTokenExpiresAt
                || ($this->securityVersions[$userId] ?? 1) !== $expectedSecurityVersion
            ) {
                return null;
            }

            $new = new SessionRecord(
                $this->nextId++,
                $newJtiHash,
                $userId,
                $tokenExpiresAt,
                $renewableUntil,
                $expectedSecurityVersion
            );
            $this->sessions[$oldJtiHash] = $old->revoke($at, 'rotated', $new->id());
            $this->sessions[$newJtiHash] = $new;
            $this->rotations[] = [$oldJtiHash, $newJtiHash];

            return $new;
        }

        public function revoke(string $jtiHash, string $reason, DateTimeImmutable $at): bool
        {
            $record = $this->sessions[$jtiHash] ?? null;
            if ($record === null || $record->revokedAt() !== null) {
                return false;
            }

            $this->sessions[$jtiHash] = $record->revoke($at, $reason);
            $this->revocations[] = [$jtiHash, $reason];

            return true;
        }

        public function revokeAllForUser(int $userId, string $reason, DateTimeImmutable $at): int
        {
            $count = 0;
            foreach ($this->sessions as $jtiHash => $record) {
                if ($record->userId() === $userId && $this->revoke($jtiHash, $reason, $at)) {
                    $count++;
                }
            }

            return $count;
        }
    }
}

namespace {
    $jwtSessionTestRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lovecards-jwt-session-' . getmypid() . DIRECTORY_SEPARATOR;
    mkdir($jwtSessionTestRoot, 0777, true);
    file_put_contents($jwtSessionTestRoot . 'private.pem', 'private-key');
    file_put_contents($jwtSessionTestRoot . 'public.pem', 'public-key');

    function app(): \Tests\Auth\SessionLifecycle\App
    {
        global $jwtSessionTestRoot;

        return new \Tests\Auth\SessionLifecycle\App($jwtSessionTestRoot);
    }
}

namespace Firebase\JWT {
    class ExpiredException extends \UnexpectedValueException
    {
    }

    class SignatureInvalidException extends \UnexpectedValueException
    {
    }

    class BeforeValidException extends \UnexpectedValueException
    {
    }

    final class Key
    {
        public function __construct(string $key, string $algorithm)
        {
        }
    }

    final class JWT
    {
        public static array $payloads = [];
        public static array $expired = [];

        public static function encode(array $payload, string $key, string $algorithm): string
        {
            $body = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');
            $token = 'header.' . $body . '.signature';
            self::$payloads[$token] = $payload;

            return $token;
        }

        public static function decode(string $token, Key $key, ?\stdClass &$headers = null): object
        {
            if (!isset(self::$payloads[$token])) {
                throw new SignatureInvalidException('invalid');
            }
            if (self::$expired[$token] ?? false) {
                throw new ExpiredException('expired');
            }

            return json_decode((string) json_encode(self::$payloads[$token]));
        }

        public static function urlsafeB64Decode(string $value): string
        {
            $padding = strlen($value) % 4;
            if ($padding > 0) {
                $value .= str_repeat('=', 4 - $padding);
            }

            return (string) base64_decode(strtr($value, '-_', '+/'));
        }

        public static function jsonDecode(string $value): object
        {
            return json_decode($value);
        }
    }
}

namespace think\facade {
    final class Config
    {
        public static array $jwt = [
            'privateKey' => 'private.pem',
            'publicKey' => 'public.pem',
            'alg' => 'RS256',
            'exp' => 600,
            'iss' => 'https://example.test',
            'cacheTime' => 120,
            'legacyBridgeUntil' => 0,
        ];

        public static function get(string $key): array
        {
            return self::$jwt;
        }
    }

    final class Cache
    {
        public static array $values = [];
        public static array $deleted = [];

        public static function get(string $key)
        {
            return self::$values[$key] ?? null;
        }

        public static function delete(string $key): void
        {
            self::$deleted[] = $key;
            unset(self::$values[$key]);
        }

        public static function pull(string $key)
        {
            $value = self::$values[$key] ?? null;
            if ($value !== null) {
                self::delete($key);
            }

            return $value;
        }
    }
}

namespace {
    use app\modules\Platform\Auth\Domain\SessionId;
    use app\modules\Platform\Auth\Infrastructure\JwtTokenService;
    use Firebase\JWT\JWT as FirebaseJwt;
    use Tests\Auth\SessionLifecycle\InMemorySessionRegistry;
    use think\facade\Cache;
    use think\facade\Config;

    require dirname(__DIR__, 2) . '/app/shared/SystemPaths/PersistentConfiguration.php';
    require $jwtSessionAuthBase . 'Infrastructure/Jwt.php';
    require $jwtSessionAuthBase . 'Infrastructure/JwtTokenService.php';

    $failures = 0;
    $tests = [];
    $test = static function (string $name, callable $callback) use (&$tests): void {
        $tests[$name] = $callback;
    };
    $assertSame = static function ($expected, $actual, string $message = ''): void {
        if ($expected !== $actual) {
            throw new \RuntimeException(($message === '' ? 'assertion failed' : $message)
                . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    };
    $assertThrows = static function (callable $callback, string $message): void {
        try {
            $callback();
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() !== $message) {
                throw new \RuntimeException('unexpected exception: ' . $exception->getMessage());
            }

            return;
        }

        throw new \RuntimeException('expected RuntimeException');
    };

    $now = new \DateTimeImmutable('@1000');
    $jtiValues = [
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        'ccccccccccccccccccccccccccccccccccccccccccc',
        'ddddddddddddddddddddddddddddddddddddddddddd',
        'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee',
        'fffffffffffffffffffffffffffffffffffffffffff',
        'ggggggggggggggggggggggggggggggggggggggggggg',
        'hhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhh',
    ];
    $makeService = static function (InMemorySessionRegistry $registry) use (&$now, &$jtiValues): JwtTokenService {
        return new JwtTokenService(
            $registry,
            static function () use (&$now): \DateTimeImmutable {
                return $now;
            },
            static function () use (&$jtiValues): string {
                return array_shift($jtiValues);
            }
        );
    };

    $test('sign stores only the JTI hash and embeds the session security version', static function () use ($makeService, $assertSame): void {
        $registry = new InMemorySessionRegistry();
        $registry->securityVersions[42] = 7;
        $token = $makeService($registry)->sign(['uid' => 42]);
        $payload = FirebaseJwt::$payloads[$token];
        $hash = SessionId::fromRaw((string) $payload['jti'])->hash();

        $assertSame([$hash], array_keys($registry->sessions));
        $assertSame(7, $payload['data']['security_version']);
        $assertSame(1600, $payload['exp']);
    });

    $test('valid session token verifies while revoked token fails closed', static function () use ($makeService, $assertSame, $assertThrows, $now): void {
        $registry = new InMemorySessionRegistry();
        $service = $makeService($registry);
        $token = $service->sign(['uid' => 42]);

        $assertSame(42, $service->verify($token)['uid']);
        $service->invalidate($token);
        $assertThrows(static fn() => $service->verify($token), 'token已失效');
        $assertSame('logout', $registry->revocations[0][1]);
    });

    $test('expired session rotates once and preserves X-New-Token contract', static function () use ($makeService, $assertSame, $assertThrows, &$now): void {
        $now = new \DateTimeImmutable('@1000');
        $registry = new InMemorySessionRegistry();
        $service = $makeService($registry);
        $oldToken = $service->sign(['uid' => 42]);
        $now = new \DateTimeImmutable('@1600');
        FirebaseJwt::$expired[$oldToken] = true;

        $result = $service->verify($oldToken);
        $assertSame(42, $result['uid']);
        $assertSame(true, isset($result['_new_token']));
        $assertSame(1, count($registry->rotations));
        $assertThrows(static fn() => $service->verify($oldToken), 'token已失效');
    });

    $test('security version change invalidates every existing session', static function () use ($makeService, $assertThrows): void {
        $registry = new InMemorySessionRegistry();
        $service = $makeService($registry);
        $token = $service->sign(['uid' => 42]);
        $registry->securityVersions[42] = 2;

        $assertThrows(static fn() => $service->verify($token), 'token已失效');
    });

    $test('legacy token migrates only before the configured absolute cutoff', static function () use ($makeService, $assertSame, $assertThrows, &$now): void {
        $now = new \DateTimeImmutable('@1000');
        $registry = new InMemorySessionRegistry();
        $service = $makeService($registry);
        Config::$jwt['legacyBridgeUntil'] = 1100;
        $legacyPayload = [
            'iss' => Config::$jwt['iss'],
            'iat' => 900,
            'nbf' => 900,
            'exp' => 1050,
            'data' => ['uid' => 42],
        ];
        $legacyToken = FirebaseJwt::encode($legacyPayload, 'private-key', 'RS256');
        Cache::$values['token_' . $legacyToken] = 1;

        $result = $service->verify($legacyToken);
        $assertSame(true, isset($result['_new_token']));
        $assertSame(['token_' . $legacyToken], Cache::$deleted);

        $secondToken = FirebaseJwt::encode($legacyPayload, 'private-key', 'RS256');
        Cache::$values['token_' . $secondToken] = 1;
        Config::$jwt['legacyBridgeUntil'] = 999;
        $assertThrows(static fn() => $service->verify($secondToken), 'token已失效');
    });

    foreach ($tests as $name => $callback) {
        try {
            $callback();
            fwrite(STDOUT, "PASS {$name}\n");
        } catch (\Throwable $exception) {
            $failures++;
            fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
        }
    }

    global $jwtSessionTestRoot;
    unlink($jwtSessionTestRoot . 'private.pem');
    unlink($jwtSessionTestRoot . 'public.pem');
    rmdir($jwtSessionTestRoot);

    if ($failures > 0) {
        exit(1);
    }

    fwrite(STDOUT, count($tests) . " JWT session lifecycle tests passed.\n");
}
