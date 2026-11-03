<?php

namespace app\api\middleware;

use app\api\ApiException;
use app\api\ApiResponse;

final class PermissionCheck
{
    public function handle($request, \Closure $next)
    {
        $routeMeta = request()->rule()->getOption('meta') ?? [];
        $access = $routeMeta['access'] ?? null;
        if (!is_array($access)) return $this->deny('接口未声明访问策略');

        $auth = $access['auth'] ?? null;
        if ($auth === 'public') return $next($request);
        if ($auth !== 'required' || !isset($request->auth)) return $this->deny('权限不足');

        $grants = $request->auth->grants();
        $allOf = $access['allOf'] ?? [];
        $anyOf = $access['anyOf'] ?? [];
        if (($allOf !== [] && !$grants->allowsAll($allOf))
            || ($anyOf !== [] && !$grants->allowsAny($anyOf))) {
            return $this->deny('权限不足');
        }

        return $next($request);
    }

    private function deny(string $message)
    {
        return ApiResponse::createForbidden($message, null, ApiException::CODE_PERMISSION_DENIED);
    }
}
