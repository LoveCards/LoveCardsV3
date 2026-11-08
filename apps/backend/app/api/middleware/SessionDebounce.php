<?php

namespace app\api\middleware;

use think\facade\Cache;
use app\api\ApiResponse;

class SessionDebounce
{
    public function handle($request, \Closure $next)
    {
        $routeName = request()->rule() && method_exists(request()->rule(), 'getName')
            ? (string) request()->rule()->getName()
            : (string) request()->path();
        $name = 'LastPostTime:' . $routeName . ':' . (string) request()->ip();
        $time = 6;

        // 计数存 Cache（生产为 Redis，多进程共享）；旧实现存 PHP 会话，
        // 对不带 Cookie 的 API 客户端完全失效。
        if (Cache::has($name)) {
            return ApiResponse::createTooMany('操作太快了，请稍后再试');
        }
        Cache::set($name, date('Y-m-d H:i:s'), $time);

        return $next($request);
    }
}
