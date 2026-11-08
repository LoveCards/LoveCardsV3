<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\api\ApiException;
use app\api\ApiResponse;
use think\facade\Cache;
use think\facade\Log;

/**
 * 登录与访客铸造节流：计数存 Cache（生产走 Redis，多进程共享），
 * 不再依赖会话 Cookie —— 旧 SessionDebounce 的会话存储对不带 Cookie
 * 的 API 客户端完全无效。
 *
 * 账号维度按 (IP, 账号) 计数，避免锁定被武器化为针对他人账号的 DoS；
 * IP 维度兜底跨账号爆破；guest 每次铸造一次性身份，按 IP 小时窗限额。
 *
 * 失败识别以响应状态为准：think\Pipeline 会把下游异常就地转换为响应
 * （每层 pipe 外层都包 try/catch），路由中间件通常收不到 ApiException；
 * ApiException catch 分支保留为不同管道语境下的兜底。
 */
class LoginThrottle
{
    private const HTTP_UNAUTHORIZED = 401;

    public function handle($request, \Closure $next)
    {
        $limits = (array) config('auth_throttle');
        $ip = (string) $request->ip();

        $rule = $request->rule();
        $route = ($rule !== null && method_exists($rule, 'getName'))
            ? (string) $rule->getName()
            : (string) $request->path();

        if ($route === 'session.guest') {
            $quotaKey = 'auth_guest:' . hash('sha256', $ip);
            $quota = max(1, (int) ($limits['guest_per_ip_hour'] ?? 120));
            $used = (int) Cache::get($quotaKey, 0);
            if ($used >= $quota) {
                return ApiResponse::createTooMany('访客访问过于频繁，请稍后再试');
            }
            Cache::set($quotaKey, $used + 1, max(60, (int) ($limits['guest_window'] ?? 3600)));
            return $next($request);
        }

        $account = mb_strtolower(trim((string) $request->post('account', '')));
        $window = max(60, (int) ($limits['window'] ?? 600));
        $accountLimit = max(1, (int) ($limits['account_fail_limit'] ?? 5));
        $ipLimit = max($accountLimit + 1, (int) ($limits['ip_fail_limit'] ?? 30));

        $failKey = 'auth_fail:' . hash('sha256', $ip . '|' . $account);
        $ipKey = 'auth_ip:' . hash('sha256', $ip);
        if ((int) Cache::get($failKey, 0) >= $accountLimit || (int) Cache::get($ipKey, 0) >= $ipLimit) {
            return ApiResponse::createTooMany('登录尝试过于频繁，请稍后再试');
        }

        try {
            $response = $next($request);
        } catch (ApiException $exception) {
            if (in_array($exception->getCode(), [
                ApiException::CODE_USER_NOT_FOUND,
                ApiException::CODE_PASSWORD_MISMATCH,
                ApiException::CODE_LOGIN_FAILED,
            ], true)) {
                $this->recordFailure($failKey, $ipKey, $ip, $account, $window, $accountLimit, $ipLimit);
            }
            throw $exception;
        }

        if ($response instanceof \think\Response && $response->getCode() === self::HTTP_UNAUTHORIZED) {
            $this->recordFailure($failKey, $ipKey, $ip, $account, $window, $accountLimit, $ipLimit);
            return $response;
        }

        Cache::delete($failKey);
        return $response;
    }

    private function recordFailure(string $failKey, string $ipKey, string $ip, string $account, int $window, int $accountLimit, int $ipLimit): void
    {
        $accountFails = (int) Cache::get($failKey, 0) + 1;
        $ipFails = (int) Cache::get($ipKey, 0) + 1;
        Cache::set($failKey, $accountFails, $window);
        Cache::set($ipKey, $ipFails, $window);
        if ($accountFails === $accountLimit || $ipFails === $ipLimit) {
            Log::warning('login throttle engaged ip=' . $ip . ' account_hash=' . substr(hash('sha256', $account), 0, 16));
        }
    }
}
