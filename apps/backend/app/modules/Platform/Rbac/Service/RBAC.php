<?php

namespace app\modules\Platform\Rbac\Service;

use think\facade\Route;
use app\shared\Authorization\Grant;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\Scope;
use app\shared\Infrastructure\Cache\CacheManager;
use app\modules\Platform\Rbac\Infrastructure\Persistence\Model\RoleGrants;
use app\modules\Platform\Rbac\Domain\CapabilityCatalog;

class RBAC
{
    public static function getAllCapabilities(): array
    {
        return CapabilityCatalog::all();
    }

    /** @return string[] Compatibility view for routes not yet migrated to access metadata. */
    public static function getUserCapabilities(array $roleIds): array
    {
        return self::getUserGrants($roleIds)->capabilityNames();
    }

    public static function getUserGrants(array $roleIds): GrantSet
    {
        if ($roleIds === []) return new GrantSet();

        if (in_array(config('system.system_roles.root'), $roleIds, true)) {
            return self::rootGrantSet();
        }

        $sorted = array_values(array_unique(array_map('intval', $roleIds)));
        sort($sorted);
        $cacheKey = CacheManager::key('rbac', 'grants', md5(implode(',', $sorted)));
        $rows = CacheManager::get('rbac', $cacheKey, function () use ($sorted) {
            return RoleGrants::whereIn('role_id', $sorted)
                ->distinct(true)
                ->field('capability,scope')
                ->select()
                ->toArray();
        }, CacheManager::TTL_LONG);

        return GrantSet::fromRows($rows);
    }

    /** 管理端高风险动作使用的非缓存读取，避免授权上限检查命中过期角色缓存。 */
    public static function getFreshUserGrants(array $roleIds): GrantSet
    {
        if ($roleIds === []) return new GrantSet();
        if (in_array(config('system.system_roles.root'), $roleIds, true)) return self::rootGrantSet();
        return GrantSet::fromRows(
            RoleGrants::whereIn('role_id', array_values(array_unique(array_map('intval', $roleIds))))
                ->distinct(true)
                ->field('capability,scope')
                ->select()
                ->toArray()
        );
    }

    /** @return string[] Compatibility view for the current admin client. */
    public static function getRoleCapabilities(int $roleId): array
    {
        return self::getRoleGrants($roleId)->capabilityNames();
    }

    public static function getRoleGrants(int $roleId): GrantSet
    {
        $cacheKey = CacheManager::key('rbac', 'role_grants', $roleId);
        $rows = CacheManager::get('rbac', $cacheKey, function () use ($roleId) {
            return RoleGrants::where('role_id', $roleId)
                ->field('capability,scope')
                ->select()
                ->toArray();
        }, CacheManager::TTL_LONG);

        return GrantSet::fromRows($rows);
    }

    public static function getFreshRoleGrants(int $roleId): GrantSet
    {
        return GrantSet::fromRows(
            RoleGrants::where('role_id', $roleId)
                ->field('capability,scope')
                ->select()
                ->toArray()
        );
    }

    public static function getRouteMeta(): array
    {
        return CacheManager::get('rbac', 'rbac:route_meta', function () {
            $result = [];
            $ruleList = Route::getRuleName()->getRuleList();

            foreach ($ruleList as $rule) {
                $name = $rule['name'] ?? '';
                if ($name === '') continue;

                $methods = $rule['method'] ?? 'GET';
                $meta = $rule['option']['meta'] ?? [];

                foreach ((array) $methods as $method) {
                    $method = strtoupper($method);
                    if ($method === 'HEAD' || $method === 'OPTIONS') continue;
                    $hash = md5($name . ':' . $method);
                    $result[$hash] = [
                        'hash' => $hash,
                        'route_name' => $name,
                        'method' => $method,
                        'name' => $meta['name'] ?? $name,
                        'group' => $meta['group'] ?? '',
                        'path' => '/' . ltrim($rule['rule'] ?? '', '/'),
                        'access' => $meta['access'] ?? null,
                    ];
                }
            }

            return $result;
        }, CacheManager::TTL_LONG);
    }

    public static function clearCache(int $roleId = 0): void
    {
        CacheManager::clearDomain('rbac');
    }

    private static function rootGrantSet(): GrantSet
    {
        $catalog = self::getAllCapabilities();
        $grants = [];
        foreach ($catalog as $capability => $_description) {
            if (str_ends_with($capability, '.all')) continue;
            $scope = array_key_exists($capability . '.all', $catalog) ? Scope::ALL : Scope::GLOBAL;
            $grants[] = new Grant($capability, $scope);
        }
        return new GrantSet($grants);
    }
}
