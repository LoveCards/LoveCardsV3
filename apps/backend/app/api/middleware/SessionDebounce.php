<?php

namespace app\api\middleware;

use think\facade\Session;
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

        $lastPostTime = Session::get($name);
        if ($lastPostTime === null || time() > strtotime((string) $lastPostTime)) {
            Session::set($name, date("Y-m-d H:i:s", strtotime('+' . $time . ' second')));
        } else {
            return ApiResponse::createTooMany('操作太快了，请稍后再试');
        }

        return $next($request);
    }
}
