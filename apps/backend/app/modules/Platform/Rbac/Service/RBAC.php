<?php

namespace app\modules\Platform\Rbac\Service;

use think\facade\Route;
use app\shared\Infrastructure\Cache\CacheManager;
use app\modules\Platform\Rbac\Infrastructure\Persistence\Model\RoleCapabilities;
use app\modules\Platform\Rbac\Domain\CapabilityCatalog;

class RBAC
{
    /**
     * 全量能力列表
     *
     * @return array capability => description
     */
    public static function getAllCapabilities(): array
    {
        return CapabilityCatalog::all();
    }

    /**
     * 获取用户所有能力列表
     *
     * @param array $rolesId 角色 ID 数组
     * @return string[]
     */
    public static function getUserCapabilities(array $rolesId): array
    {
        if (empty($rolesId)) {
            return [];
        }

        // root 角色拥有所有能力
        if (in_array(config('system.system_roles.root'), $rolesId)) {
            return array_keys(self::getAllCapabilities());
        }

        $sorted = $rolesId;
        sort($sorted);
        $cacheKey = CacheManager::key('rbac', 'caps', md5(implode(',', $sorted)));

        return CacheManager::get('rbac', $cacheKey, function () use ($rolesId) {
            return RoleCapabilities::whereIn('role_id', $rolesId)
                ->distinct()
                ->column('capability');
        }, CacheManager::TTL_LONG);
    }

    /**
     * 获取角色的能力列表
     *
     * @param int $roleId
     * @return string[]
     */
    public static function getRoleCapabilities(int $roleId): array
    {
        $cacheKey = CacheManager::key('rbac', 'role_caps', $roleId);

        return CacheManager::get('rbac', $cacheKey, function () use ($roleId) {
            return RoleCapabilities::where('role_id', $roleId)
                ->column('capability');
        }, CacheManager::TTL_LONG);
    }

    /**
     * 扫描路由定义，返回所有权限元数据（兼容旧接口）
     *
     * @return array hash => {route_name, method, name, public, ...}
     */
    public static function getRouteMeta(): array
    {
        return CacheManager::get('rbac', 'rbac:route_meta', function () {
            $result = [];
            $ruleList = Route::getRuleName()->getRuleList();

            foreach ($ruleList as $rule) {
                $name = $rule['name'] ?? '';
                if (empty($name)) {
                    continue;
                }

                $methods = $rule['method'] ?? 'GET';
                $meta = $rule['option']['meta'] ?? [];

                foreach ((array) $methods as $method) {
                    $method = strtoupper($method);
                    if ($method === 'HEAD' || $method === 'OPTIONS') {
                        continue;
                    }
                    $hash = md5($name . ':' . $method);
                    $result[$hash] = [
                        'hash'       => $hash,
                        'route_name' => $name,
                        'method'     => $method,
                        'name'       => $meta['name'] ?? $name,
                        'group'      => $meta['group'] ?? '',
                        'path'       => '/' . ltrim($rule['rule'] ?? '', '/'),
                        'public'     => $meta['public'] ?? false,
                        'caps'       => $meta['caps'] ?? [],
                    ];
                }
            }

            return $result;
        }, CacheManager::TTL_LONG);
    }

    /**
     * 清除角色相关缓存
     */
    public static function clearCache(int $roleId = 0): void
    {
        CacheManager::clearDomain('rbac');
    }
}
