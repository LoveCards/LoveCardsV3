<?php
declare(strict_types=1);

// LoginThrottle 行为基线（T1 认证加固）：
// 1) 同 (IP, 账号) 连续口令失败达到上限后，连正确口令也拒绝（锁定窗口）；
// 2) 锁定按 (IP, 账号) 维度隔离，不殃及他人；
// 3) 成功登录清零该维度计数；
// 4) 同 IP 跨账号失败达到 IP 上限后，任意账号都被挡；
// 5) session/guest 铸造按 IP 小时窗限额；
// 6) 下游以异常上抛失败（非 Pipeline 语境）同样计数（兼容兜底）。
//
// 注意：真实 HTTP 下 think\Pipeline 会把下游异常就地转成响应，路由中间件
// 收到的是 401 响应而不是异常；替身必须还原该形态，单测才与线上同构。

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

// mode: ok => 下游返回 200；authfail => 下游返回渲染后的 401 响应（线上真实形态）；
// throw => 下游直接上抛 ApiException（非 Pipeline 语境的兼容路径）。
$runLogin = static function (LoginThrottle $throttle, string $routeName, string $account, string $ip, string $mode) use ($makeRequest): array {
    $passed = false;
    // 替身遵守真实管道契约：$next 必须收到 request 参数，否则视为未透传。
    $next = static function (mixed $relayed = '__missing__') use (&$passed, $mode) {
        $passed = $relayed !== '__missing__';
        if ($mode === 'authfail') {
            return \think\Response::create('{"success":false}', 'json', 401);
        }
        if ($mode === 'throw') {
            throw ApiException::unauthorized('密码不匹配', ApiException::CODE_PASSWORD_MISMATCH);
        }
        return \think\Response::create('{"success":true}', 'json', 200);
    };
    try {
        $result = $throttle->handle($makeRequest($routeName, $account, $ip), $next);
        $status = $result instanceof \think\Response ? $result->getCode() : null;
        return ['passed' => $passed, 'status' => $status];
    } catch (ApiException $exception) {
        return ['passed' => $passed, 'status' => null, 'code' => $exception->getCode()];
    }
};

$tests = [];
$tests['account dimension blocks after the failure limit even for the right password'] = static function () use ($throttle, $runLogin, $accountLimit, $assert): void {
    for ($i = 0; $i < $accountLimit; $i++) {
        $attempt = $runLogin($throttle, 'session.login', 'victim@example.com', '198.51.100.9', 'authfail');
        $assert($attempt['passed'] && $attempt['status'] === 401, 'failures below the limit must pass through as 401');
    }
    $locked = $runLogin($throttle, 'session.login', 'victim@example.com', '198.51.100.9', 'ok');
    $assert(!$locked['passed'], 'the account dimension must block further attempts once the limit is hit');
    $assert($locked['status'] === 429, 'the throttle must answer with HTTP 429 while locked');
};
$tests['the account block is scoped to the ip-account pair'] = static function () use ($throttle, $runLogin, $assert): void {
    $otherIp = $runLogin($throttle, 'session.login', 'victim@example.com', '198.51.100.10', 'ok');
    $assert($otherIp['passed'] && $otherIp['status'] === 200, 'the block must be scoped to the ip-account pair');
};
$tests['a successful login clears the counter'] = static function () use ($throttle, $runLogin, $accountLimit, $assert): void {
    for ($i = 0; $i < $accountLimit - 1; $i++) {
        $runLogin($throttle, 'session.login', 'recovery@example.com', '198.51.100.11', 'authfail');
    }
    $heal = $runLogin($throttle, 'session.login', 'recovery@example.com', '198.51.100.11', 'ok');
    $assert($heal['passed'] && $heal['status'] === 200, 'a successful login must clear the counter');
    for ($i = 0; $i < $accountLimit - 1; $i++) {
        $again = $runLogin($throttle, 'session.login', 'recovery@example.com', '198.51.100.11', 'authfail');
        $assert($again['passed'] && $again['status'] === 401, 'the counter must restart after a success');
    }
};
$tests['the ip dimension blocks distributed failures without leaking to other ips'] = static function () use ($throttle, $runLogin, $ipLimit, $assert): void {
    for ($i = 0; $i < $ipLimit; $i++) {
        $runLogin($throttle, 'session.login', 'rotate-' . $i . '@example.com', '203.0.113.20', 'authfail');
    }
    $ipBlocked = $runLogin($throttle, 'session.login', 'rotate-new@example.com', '203.0.113.20', 'ok');
    $assert(!$ipBlocked['passed'] && $ipBlocked['status'] === 429, 'the ip dimension must block after enough distributed failures');
    $otherIpLogin = $runLogin($throttle, 'session.login', 'rotate-new@example.com', '203.0.113.21', 'ok');
    $assert($otherIpLogin['passed'] && $otherIpLogin['status'] === 200, 'the ip block must not leak to other ips');
};
$tests['guest mints stay inside the per-ip hourly quota'] = static function () use ($throttle, $runLogin, $guestQuota, $assert): void {
    for ($i = 0; $i < $guestQuota; $i++) {
        $mint = $runLogin($throttle, 'session.guest', '', '198.51.100.30', 'ok');
        $assert($mint['passed'] && $mint['status'] === 200, 'guest mints below quota must pass');
    }
    $quotaBlocked = $runLogin($throttle, 'session.guest', '', '198.51.100.30', 'ok');
    $assert(!$quotaBlocked['passed'] && $quotaBlocked['status'] === 429, 'guest mints must stop at the per-ip quota with 429');
    $otherGuestIp = $runLogin($throttle, 'session.guest', '', '198.51.100.31', 'ok');
    $assert($otherGuestIp['passed'], 'the guest quota must be scoped per ip');
};
$tests['downstream exceptions are counted as failures too'] = static function () use ($throttle, $runLogin, $accountLimit, $assert): void {
    for ($i = 0; $i < $accountLimit; $i++) {
        $attempt = $runLogin($throttle, 'session.login', 'legacy@example.com', '198.51.100.40', 'throw');
        $assert($attempt['passed'] && ($attempt['code'] ?? 0) === ApiException::CODE_PASSWORD_MISMATCH, 'exceptions below the limit must pass through');
    }
    $locked = $runLogin($throttle, 'session.login', 'legacy@example.com', '198.51.100.40', 'ok');
    $assert(!$locked['passed'] && $locked['status'] === 429, 'exception-path failures must also lock the account dimension');
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
