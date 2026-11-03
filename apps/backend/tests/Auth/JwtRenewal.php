<?php

declare(strict_types=1);

namespace Tests\Auth {
    final class App
    {
        private $rootPath;

        public function __construct(string $rootPath)
        {
            $this->rootPath = $rootPath;
        }

        public function getRootPath(): string
        {
            return $this->rootPath;
        }
    }
}

namespace {
    $jwtTestRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lovecards-jwt-test-' . getmypid() . DIRECTORY_SEPARATOR;
    mkdir($jwtTestRoot, 0777, true);
    file_put_contents($jwtTestRoot . 'private.pem', 'private-key');
    file_put_contents($jwtTestRoot . 'public.pem', 'public-key');

    function app(): \Tests\Auth\App
    {
        global $jwtTestRoot;

        return new \Tests\Auth\App($jwtTestRoot);
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
        public static $decodeCalls = 0;
        public static $encodedPayload = [];

        public static function decode(string $token, Key $key, ?\stdClass &$headers = null)
        {
            self::$decodeCalls++;
            throw new ExpiredException('Expired token');
        }

        public static function encode(array $payload, string $key, string $algorithm): string
        {
            self::$encodedPayload = $payload;

            return 'renewed-token';
        }

        public static function urlsafeB64Decode(string $value): string
        {
            $padding = strlen($value) % 4;
            if ($padding > 0) {
                $value .= str_repeat('=', 4 - $padding);
            }

            return base64_decode(strtr($value, '-_', '+/'));
        }

        public static function jsonDecode(string $value)
        {
            return json_decode($value);
        }
    }
}

namespace Tests\Auth {
    final class CacheTag
    {
        public static $tokens = [];
        public static $deleted = [];

        public function get(string $key)
        {
            return self::$tokens[$key] ?? null;
        }

        public function delete(string $key): void
        {
            self::$deleted[] = $key;
            unset(self::$tokens[$key]);
        }
    }
}

namespace think\facade {
    final class Config
    {
        public static function get(string $key): array
        {
            return [
                'privateKey' => 'private.pem',
                'publicKey' => 'public.pem',
                'alg' => 'RS256',
                'exp' => 600,
                'iss' => 'https://example.test',
                'cacheTime' => 120,
            ];
        }
    }

    final class Cache
    {
        public static function get(string $key)
        {
            return \Tests\Auth\CacheTag::$tokens[$key] ?? null;
        }

        public static function delete(string $key): void
        {
            \Tests\Auth\CacheTag::$deleted[] = $key;
            unset(\Tests\Auth\CacheTag::$tokens[$key]);
        }

        public static function tag(string $tag): \Tests\Auth\CacheTag
        {
            return new \Tests\Auth\CacheTag();
        }
    }
}

namespace app\shared\Infrastructure\Cache {
    final class CacheManager
    {
        public static $setCalls = [];

        public static function set(string $domain, string $key, $value, int $ttl = 0): void
        {
            self::$setCalls[] = compact('domain', 'key', 'value', 'ttl');
        }
    }
}

namespace {
    use app\modules\Platform\Auth\Infrastructure\Jwt;
    use Firebase\JWT\JWT as FirebaseJwt;

    require dirname(__DIR__, 2) . '/app/shared/SystemPaths/PersistentConfiguration.php';
    require dirname(__DIR__, 2) . '/app/modules/Platform/Auth/Infrastructure/Jwt.php';

    $failures = 0;

    $assertSame = static function ($expected, $actual, string $message): void {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                $message . ': expected ' . var_export($expected, true)
                . ', got ' . var_export($actual, true)
            );
        }
    };

    $payload = json_encode([
        'iss' => 'https://example.test',
        'iat' => 100,
        'exp' => 200,
        'data' => ['uid' => 42],
    ]);
    $body = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $expiredToken = 'header.' . $body . '.signature';

    try {
        $result = Jwt::inspect($expiredToken);
        $assertSame(42, $result['data']['uid'], 'expired claims preserve uid');
        $assertSame(true, $result['expired'], 'expired claims are marked');
        $assertSame(1, FirebaseJwt::$decodeCalls, 'expired token is not decoded twice');
        fwrite(STDOUT, "PASS expired token exposes verified claims once\n");
    } catch (\Throwable $exception) {
        $failures++;
        fwrite(STDERR, "FAIL expired token inspection: {$exception->getMessage()}\n");
    }

    try {
        Jwt::inspect('invalid-token');
        $failures++;
        fwrite(STDERR, "FAIL malformed expired token: expected RuntimeException\n");
    } catch (\RuntimeException $exception) {
        try {
            $assertSame('token内容无效', $exception->getMessage(), 'malformed payload is rejected');
            fwrite(STDOUT, "PASS malformed expired token is rejected\n");
        } catch (\Throwable $assertionFailure) {
            $failures++;
            fwrite(STDERR, "FAIL malformed expired token: {$assertionFailure->getMessage()}\n");
        }
    }

    global $jwtTestRoot;
    unlink($jwtTestRoot . 'private.pem');
    unlink($jwtTestRoot . 'public.pem');
    rmdir($jwtTestRoot);

    if ($failures > 0) {
        exit(1);
    }

    fwrite(STDOUT, "2 JWT codec regression tests passed.\n");
}
