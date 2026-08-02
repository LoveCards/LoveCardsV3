<?php

namespace app\api\middleware;

use think\facade\Session;
use app\api\ApiResponse;

class SessionDebounce
{
    public function handle($request, \Closure $next)
    {
        $name = 'LastPostTime';
        $time = 6;

        $lastPostTime = Session::get($name);
        if ($lastPostTime === null || time() > strtotime((string) $lastPostTime)) {
            Session::set($name, date("Y-m-d H:i:s", strtotime('+' . $time . ' second')));
        } else {
            return ApiResponse::createError('操作失败', ['您的操作太快了，稍后再试试试吧']);
        }

        return $next($request);
    }
}
