<?php
declare(strict_types=1);

namespace think\facade {
    final class Request
    {
        public static function param(string $key = '', mixed $default = null): mixed
        {
            return $default;
        }
    }
}

namespace app\modules\Platform\Captcha\Service {
    final class Captcha
    {
        public static bool $enabled = true;
        public static bool $valid = false;
        public static int $verifyCalls = 0;

        public static function isEnabled(string $type): bool { return self::$enabled; }
        public static function driver(string $type): object
        {
            return new class {
                public function verify(array $params): bool
                {
                    Captcha::$verifyCalls++;
                    return Captcha::$valid;
                }
            };
        }
    }
}

namespace app\api {
    final class ApiResponse
    {
        public static function createUnauthorized(string $message): array { return ['status' => 401, 'message' => $message]; }
        public static function createUnavailable(string $message): array { return ['status' => 503, 'message' => $message]; }
    }
}

namespace {
    require dirname(__DIR__, 2) . '/app/api/middleware/CaptchaCheck.php';

    use app\api\middleware\CaptchaCheck;
    use app\modules\Platform\Captcha\Service\Captcha;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };
    $next = static fn ($request): array => ['status' => 200];
    $middleware = new CaptchaCheck();

    foreach (['GET', 'DELETE'] as $method) {
        Captcha::$verifyCalls = 0;
        $request = new class ($method) {
            public function __construct(private string $method) {}
            public function method(): string { return $this->method; }
        };
        $result = $middleware->handle($request, $next, ['type' => 'captcha']);
        $assert($result['status'] === 200 && Captcha::$verifyCalls === 0, "$method must not require human verification");
    }

    foreach (['POST', 'PATCH'] as $method) {
        Captcha::$verifyCalls = 0;
        $request = new class ($method) {
            public function __construct(private string $method) {}
            public function method(): string { return $this->method; }
        };
        $result = $middleware->handle($request, $next, ['type' => 'captcha']);
        $assert($result['status'] === 401 && Captcha::$verifyCalls === 1, "$method must reject an invalid challenge");
    }

    Captcha::$enabled = false;
    $request = new class {
        public function method(): string { return 'POST'; }
    };
    $assert($middleware->handle($request, $next, ['type' => 'captcha'])['status'] === 200, 'disabled policy must preserve existing write behavior');

    fwrite(STDOUT, "PASS card captcha method baseline\n");
}
