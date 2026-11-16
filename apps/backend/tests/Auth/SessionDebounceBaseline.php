<?php
declare(strict_types=1);

namespace think\facade {
    final class Cache
    {
        public static array $values = [];
        public static function has(string $key): bool { return array_key_exists($key, self::$values); }
        public static function set(string $key, mixed $value, int $ttl): void { self::$values[$key] = $value; }
    }
}

namespace app\api {
    final class ApiResponse
    {
        public static function createTooMany(string $message): array { return ['status' => 429, 'message' => $message]; }
    }
}

namespace {
    require dirname(__DIR__, 2) . '/app/api/middleware/SessionDebounce.php';

    use app\api\middleware\SessionDebounce;
    use think\facade\Cache;

    $current = null;
    function request(): object { global $current; return $current; }
    $makeRequest = static fn (string $route, string $ip): object => new class ($route, $ip) {
        public function __construct(private string $route, private string $ip) {}
        public function rule(): object { return new class ($this->route) {
            public function __construct(private string $name) {}
            public function getName(): string { return $this->name; }
        }; }
        public function path(): string { return '/api/' . $this->route; }
        public function ip(): string { return $this->ip; }
    };
    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };
    $middleware = new SessionDebounce();
    $next = static fn (object $request): array => ['status' => 200];

    $current = $makeRequest('session.register', '127.0.0.1');
    $assert($middleware->handle($current, $next)['status'] === 200, 'first register request must pass');
    $assert($middleware->handle($current, $next)['status'] === 429, 'repeat register request must be throttled');
    $current = $makeRequest('session.captcha', '127.0.0.1');
    $assert($middleware->handle($current, $next)['status'] === 200, 'different route must have separate debounce key');
    $current = $makeRequest('session.register', '127.0.0.2');
    $assert($middleware->handle($current, $next)['status'] === 200, 'different IP must have separate debounce key');
    $assert(count(Cache::$values) === 3, 'debounce must store one key per route and IP');

    fwrite(STDOUT, "PASS session debounce baseline\n");
}
