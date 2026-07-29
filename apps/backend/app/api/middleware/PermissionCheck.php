<?php

namespace app\api\middleware;

use app\api\ApiResponse;
use app\api\ApiException;

class PermissionCheck
{
    public function handle($request, \Closure $next)
    {
        $routeMeta = request()->rule()->getOption('meta') ?? [];

        // 公开路由 — 直接放行（由路由 meta.public 标记）
        if ($routeMeta['public'] ?? false) {
            return $next($request);
        }

        // 获取路由所需能力
        $requiredCaps = $routeMeta['caps'] ?? [];

        // 无 caps 定义 → 降级为路由名
        if (empty($requiredCaps)) {
            $routeName = request()->rule()->getName();
            $requiredCaps = [$routeName];
        }

        if (!$request->auth->hasAnyCapability($requiredCaps)) {
            return ApiResponse::createForbidden(
                '权限不足',
                null,
                ApiException::CODE_PERMISSION_DENIED
            );
        }

        return $next($request);
    }
}
