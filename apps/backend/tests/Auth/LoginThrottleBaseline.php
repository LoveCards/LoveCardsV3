<?php
declare(strict_types=1);

// LoginThrottle 行为基线（T1 认证加固）：
// 1) 同 (IP, 账号) 连续口令失败达到上限后，连正确口令也拒绝（锁定窗口）；
// 2) 锁定按 (IP, 账号) 维度隔离，不殃及他人；
// 3) 成功登录清零该维度计数；
// 4) 同 IP 跨账号失败达到 IP 上限后，任意账号都被挡；
// 5) session/guest 铸造按 IP 小时窗限额。

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\api\ApiException;
use app\api\middleware\LoginThrottle;
use think\Container;

$app = new \think\App(dirname(__DIR__, 2));
$app->setRuntimePath(sys_get_temp_dir() . '/lc-throttle-runtime/');
$app->initialize();

// Cache 门面解析容器 'cache'，这里注入纯内存替身（同 MediaGrantExpiry 模式）。
$cache = new class {
    public array $store = [];
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $this->store[$key] = $value;
        return true;
    }
    public function delete(string $key): bool
    {
        unset($this->store[$key]);
        return true;
    }
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }
};
Container::getInstance()->instance('cache', $cache);

$makeRequest = static function (string $routeName, string $account, string $ip) {
    return new class($routeName, $account, $ip) {
        public function __construct(private string $routeName, private string $account, private string $ip)
        {
        }
        public function post(string $name = '', mixed $default = null): mixed
        {
            return $name === 'account' ? $this->account : $default;
        }
        public function ip(): string
        {
            return $this->ip;
        }
        public function rule(): mixed
        {
            return new class($this->routeName) {
                public function __construct(private string $name)
                {
                }
                public function getName(): string
                {
                    return $this->name;
                }
            };
        }
        public function path(): string
        {
            return '/api/' . str_replace('.', '/', $this->routeName);
        }
    };
};

// 中间件尚不存在时必须以 FAIL 退出（而不是被框架异常处理器吞成 0）。
if (!class_exists(LoginThrottle::class)) {
    fwrite(STDERR, "FAIL LoginThrottle middleware must exist under app\\api\\middleware\n");
    exit(1);
}

$throttle = new LoginThrottle();
$limits = (array) config('auth_throttle');
$accountLimit = (int) ($limits['account_fail_limit'] ?? 0);
$ipLimit = (int) ($limits['ip_fail_limit'] ?? 0);
$guestQuota = (int) ($limits['guest_per_ip_hour'] ?? 0);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert($accountLimit >= 1 && $ipLimit > $accountLimit && $guestQuota >= 1, 'auth throttle config must define sane limits');

$runLogin = static function (LoginThrottle $throttle, string $routeName, string $account, string $ip, bool $willFail) use ($makeRequest): array {
    $passed = false;
    $next = static function () use (&$passed, $willFail) {
        $passed = true;
        if ($willFail) {
            throw ApiException::unauthorized('密码不匹配', ApiException::CODE_PASSWORD_MISMATCH);
        }
        return 'response-ok';
    };
    try {
        $result = $throttle->handle($makeRequest($routeName, $account, $ip), $next);
        return ['passed' => $passed, 'result' => $result];
    } catch (ApiException $exception) {
        return ['passed' => $passed, 'result' => null, 'code' => $exception->getCode()];
    }
};

$tests = [];
$tests['account dimension blocks after the failure limit even for the right password'] = static function () use ($throttle, $runLogin, $accountLimit, $assert): void {
    for ($i = 0; $i < $accountLimit; $i++) {
        $attempt = $runLogin($throttle, 'session.login', 'victim@example.com', '198.51.100.9', true);
        $assert($attempt['passed'] && ($attempt['code'] ?? 0) === ApiException::CODE_PASSWORD_MISMATCH, 'failures below the limit must pass through');
    }
    $locked = $runLogin($throttle, 'session.login', 'victim@example.com', '198.51.100.9', false);
    $assert(!$locked['passed'], 'the account dimension must block further attempts once the limit is hit');
    $assert($locked['result'] instanceof \think\Response, 'the throttle must answer with an API response, not an exception');
};
$tests['the account block is scoped to the ip-account pair'] = static function () use ($throttle, $runLogin, $assert): void {
    $otherIp = $runLogin($throttle, 'session.login', 'victim@example.com', '198.51.100.10', false);
    $assert($otherIp['passed'] && $otherIp['result'] === 'response-ok', 'the block must be scoped to the ip-account pair');
};
$tests['a successful login clears the counter'] = static function () use ($throttle, $runLogin, $accountLimit, $assert): void {
    for ($i = 0; $i < $accountLimit - 1; $i++) {
        $runLogin($throttle, 'session.login', 'recovery@example.com', '198.51.100.11', true);
    }
    $heal = $runLogin($throttle, 'session.login', 'recovery@example.com', '198.51.100.11', false);
    $assert($heal['passed'] && $heal['result'] === 'response-ok', 'a successful login must clear the counter');
    for ($i = 0; $i < $accountLimit - 1; $i++) {
        $again = $runLogin($throttle, 'session.login', 'recovery@example.com', '198.51.100.11', true);
        $assert($again['passed'], 'the counter must restart after a success');
    }
};
$tests['the ip dimension blocks distributed failures without leaking to other ips'] = static function () use ($throttle, $runLogin, $ipLimit, $assert): void {
    for ($i = 0; $i < $ipLimit; $i++) {
        $runLogin($throttle, 'session.login', 'rotate-' . $i . '@example.com', '203.0.113.20', true);
    }
    $ipBlocked = $runLogin($throttle, 'session.login', 'rotate-new@example.com', '203.0.113.20', false);
    $assert(!$ipBlocked['passed'], 'the ip dimension must block after enough distributed failures');
    $otherIpLogin = $runLogin($throttle, 'session.login', 'rotate-new@example.com', '203.0.113.21', false);
    $assert($otherIpLogin['passed'], 'the ip block must not leak to other ips');
};
$tests['guest mints stay inside the per-ip hourly quota'] = static function () use ($throttle, $runLogin, $guestQuota, $assert): void {
    for ($i = 0; $i < $guestQuota; $i++) {
        $mint = $runLogin($throttle, 'session.guest', '', '198.51.100.30', false);
        $assert($mint['passed'] && $mint['result'] === 'response-ok', 'guest mints below quota must pass');
    }
    $quotaBlocked = $runLogin($throttle, 'session.guest', '', '198.51.100.30', false);
    $assert(!$quotaBlocked['passed'], 'guest mints must stop at the per-ip quota');
    $otherGuestIp = $runLogin($throttle, 'session.guest', '', '198.51.100.31', false);
    $assert($otherGuestIp['passed'], 'the guest quota must be scoped per ip');
};

$failures = 0;
foreach ($tests as $name => $run) {
    try {
        $run();
    } catch (Throwable $exception) {
        $failures++;
        fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
    }
}
if ($failures > 0) {
    exit(1);
}
fwrite(STDOUT, count($tests) . " login throttle tests passed.\n");
