<?php

namespace app\modules\Platform\Rbac\Service;

use think\facade\Db;
use app\modules\Platform\Rbac\Infrastructure\Persistence\Model\Roles as RolesModel;
use app\modules\Platform\Rbac\Infrastructure\Persistence\Model\Capabilities;
use app\modules\Platform\Rbac\Infrastructure\Persistence\Model\RoleGrants;
use app\api\ApiException;
use app\shared\Infrastructure\Persistence\ModelQuery;
use app\shared\Infrastructure\Cache\CacheManager;
use app\modules\Platform\Rbac\Domain\SystemRolePolicy;
use app\modules\Platform\Rbac\Domain\CapabilityCatalog;
use app\shared\Authorization\Scope;
use app\shared\Authorization\GrantSet;

class Roles
{
    public static function batchOperate(string $method, array $ids, int $uid, GrantSet $grants): void
    {
        if (!$grants->allows('roles.delete', Scope::GLOBAL)) {
            throw ApiException::forbidden('鏉冮檺涓嶈冻');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            throw ApiException::badRequest('no role ids supplied');
        }
        if (!in_array($method, ['delete', 'trash', 'restore', 'hard_delete'], true)) {
            throw ApiException::badRequest('涓嶆敮鎸佺殑鎿嶄綔');
        }

        $roles = RolesModel::withTrashed()->whereIn('id', $ids)->select();
        if ($roles->count() !== count($ids)) {
            throw ApiException::notFound('role not found');
        }
        foreach ($roles as $role) {
            if ($role->is_system && in_array($method, ['delete', 'trash', 'hard_delete'], true)) {
                throw ApiException::badRequest('绯荤粺瑙掕壊涓嶅彲鍒犻櫎');
            }
        }

        if (in_array($method, ['delete', 'trash'], true)) {
            RolesModel::whereIn('id', $ids)->delete();
            return;
        }
        if ($method === 'restore') {
            RolesModel::withTrashed()->onlyTrashed()->whereIn('id', $ids)->update(['deleted_at' => null]);
            return;
        }

        Db::startTrans();
        try {
            RoleGrants::whereIn('role_id', $ids)->delete();
            Db::table('roles')->whereIn('id', $ids)->delete();
            Db::commit();
            foreach ($ids as $roleId) RBAC::clearCache($roleId);
        } catch (\Throwable $e) {
            Db::rollback();
            throw ApiException::error('鍒犻櫎瑙掕壊澶辫触', ApiException::CODE_SYSTEM_ERROR, null, $e);
        }
    }

    public static function Index(array $params, array $caps = []): array
    {
        $params['search_default_key'] = 'name';
        // roles.read is currently the module-wide management capability.
        $result = ModelQuery::make(RolesModel::class)
            ->trashScope((int) ($params['show_deleted'] ?? 0), in_array('roles.read', $caps, true))
            ->getPaginate($params);
        return $result->toArray();
    }

    public static function createRole(array $data): string
    {
        Db::startTrans();
        try {
            if (isset($data['slug'])) {
                $exists = RolesModel::where('slug', $data['slug'])->find();
                if ($exists) {
                    throw ApiException::badRequest('角色标识已存在');
                }
            }

            $result = RolesModel::create($data);
            Db::commit();
            return $result->id;
        } catch (\Throwable $th) {
            Db::rollback();
            if ($th instanceof ApiException) {
                throw $th;
            }
            throw ApiException::error('创建角色失败', ApiException::CODE_SYSTEM_ERROR, null, $th);
        }
    }

    public static function updateRole(int $id, array $data): void
    {
        Db::startTrans();
        try {
            $role = RolesModel::find($id);
            if (!$role) {
                throw ApiException::notFound('角色不存在');
            }

            if ($role->is_system && isset($data['slug']) && $data['slug'] !== $role->slug) {
                throw ApiException::badRequest('系统角色不可修改标识');
            }

            if (isset($data['slug'])) {
                $exists = RolesModel::where('slug', $data['slug'])
                    ->where('id', '<>', $id)
                    ->find();
                if ($exists) {
                    throw ApiException::badRequest('角色标识已存在');
                }
            }

            RolesModel::update($data, ['id' => $id]);
            Db::commit();
        } catch (\Throwable $th) {
            Db::rollback();
            if ($th instanceof ApiException) {
                throw $th;
            }
            throw ApiException::error('更新角色失败', ApiException::CODE_SYSTEM_ERROR, null, $th);
        }
    }

    public static function Get(int $id): RolesModel
    {
        $role = RolesModel::find($id);
        if (!$role) {
            throw ApiException::notFound('角色不存在');
        }
        return $role;
    }

    public static function deleteRoles(int $id): void
    {
        $data = [$id];

        $systemRoles = RolesModel::whereIn('id', $data)
            ->where('is_system', 1)
            ->select();
        if (!$systemRoles->isEmpty()) {
            $names = $systemRoles->column('name');
            throw ApiException::badRequest('系统角色不可删除：' . implode(', ', $names));
        }

        Db::startTrans();
        try {
            RoleGrants::where('role_id', 'in', $data)->delete();
            RolesModel::destroy($data);
            Db::commit();

            foreach ($data as $roleId) {
                RBAC::clearCache($roleId);
            }
        } catch (\Throwable $th) {
            Db::rollback();
            if ($th instanceof ApiException) {
                throw $th;
            }
            throw ApiException::error('删除角色失败', ApiException::CODE_SYSTEM_ERROR, null, $th);
        }
    }

    /**
     * 分配能力（用 capability 字符串数组）
     *
     * @param int      $roleId 角色 ID
     * @param string[] $caps   能力字符串数组
     */
    public static function assignCapabilities(int $roleId, array $caps): void
    {
        Db::startTrans();
        try {
            $role = RolesModel::find($roleId);
            if (!$role) {
                throw ApiException::notFound('角色不存在');
            }

            // 校验能力字符串存在性
            $invalidCaps = [];
            foreach ($caps as $cap) {
                $name = is_array($cap) ? (string) ($cap['capability'] ?? '') : (string) $cap;
                $name = str_ends_with($name, '.all') ? substr($name, 0, -4) : $name;
                if (!array_key_exists($name, CapabilityCatalog::canonical())) $invalidCaps[] = $name;
            }
            if (!empty($invalidCaps)) {
                throw ApiException::badRequest('不存在的能力：' . implode(', ', $invalidCaps));
            }

            $grants = self::normalizeGrants($caps);
            RoleGrants::where('role_id', $roleId)->delete();

            foreach ($grants as $grant) {
                RoleGrants::create([
                    'role_id' => $roleId,
                    'capability' => $grant['capability'],
                    'scope' => $grant['scope'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            Db::commit();
            RBAC::clearCache($roleId);
        } catch (\Throwable $th) {
            Db::rollback();
            if ($th instanceof ApiException) {
                throw $th;
            }
            throw ApiException::error('分配能力失败', ApiException::CODE_SYSTEM_ERROR, null, $th);
        }
    }

    /**
     * 获取角色的能力列表
     *
     * @param int $roleId
     * @return string[]
     */
    public static function getRoleCapabilities(int $roleId): array
    {
        $role = RolesModel::find($roleId);
        if (!$role) {
            throw ApiException::notFound('角色不存在');
        }

        return RBAC::getRoleGrants($roleId)->toArray();
    }

    /**
     * 获取系统角色能力分配矩阵
     *
     * 返回系统角色 ID => capability 数组的映射。
     * root 使用空数组，由调用者用 getAllCapabilities() 填充。
     * 此方法为 reseed() 和 seedSystemCapabilities() 共享，保持单一事实来源。
     *
     * @return array role_id => string[]
     */
    public static function getSystemRoleCapabilityMatrix(): array
    {
        return SystemRolePolicy::capabilityMatrix(config('system.system_roles'));
    }

    /**
     * 重新 seed 所有角色能力（破坏性）
     *
     * 删除并重新插入所有角色能力。仅适用于手动触发或升级维护。
     * 自定义角色的能力会被删除，使用时必须确认。
     *
     * @return array 统计信息
     */
    public static function reseed(): array
    {
        CacheManager::clearDomain('rbac');

        $roleCaps = self::getSystemRoleCapabilityMatrix();

        // Root 填充所有能力
        $roles = config('system.system_roles');
        $roleCaps[$roles['root']] = array_keys(RBAC::getAllCapabilities());

        Db::startTrans();
        try {
            self::syncCapabilityCatalog();
            Db::table('role_grants')->delete(true);

            $rows = [];
            foreach ($roleCaps as $roleId => $caps) {
                foreach (self::normalizeGrants($caps) as $grant) {
                    $rows[] = [
                        'role_id' => $roleId,
                        'capability' => $grant['capability'],
                        'scope' => $grant['scope'],
                        'created_at' => date('Y-m-d H:i:s'),
                    ];
                }
            }
            RoleGrants::insertAll($rows);

            Db::commit();
            CacheManager::clearDomain('rbac');

            return [
                'total' => count($rows),
                'guest' => count($roleCaps[$roles['guest']]),
                'user'  => count($roleCaps[$roles['user']]),
                'admin' => count($roleCaps[$roles['admin']]),
                'root'  => count($roleCaps[$roles['root']]),
            ];
        } catch (\Throwable $e) {
            Db::rollback();
            throw ApiException::error('Reseed 失败', ApiException::CODE_SYSTEM_ERROR, null, $e);
        }
    }

    /**
     * 非破坏性初始化系统角色能力
     *
     * 只补充系统角色缺失的 capability，不删除任何现有数据。
     * 可安全重复调用；自定义角色的能力完全不受影响。
     * 与 migration SQL 中的 seed 逻辑保持一致。
     *
     * 写入任何 capability 前先验证系统角色身份：
     *   - 从 config('system.system_roles') 获取唯一锚点
     *   - 验证四个 ID 唯一
     *   - 必须存在 4 个系统角色
     *   - 逐行验证 slug 映射和 is_system=1
     *   - 所有验证必须全部通过才进入写入阶段
     *   - 写入阶段使用事务，异常回滚，仅在 commit 后清理 cache
     *
     * @return array 每个角色插入的行数
     * @throws ApiException 角色映射验证失败或写入失败时
     */
    public static function seedSystemCapabilities(): array
    {
        // 1. 从 config 获取系统角色锚点
        $roles = config('system.system_roles');
        // 预期: ['root' => 1, 'admin' => 2, 'user' => 3, 'guest' => 4]
        $expectedIds = array_values($roles);

        // 2. 验证四个 ID 唯一
        try {
            SystemRolePolicy::assertConfigAnchors($roles);
        } catch (\RuntimeException $e) {
            throw ApiException::error($e->getMessage(), ApiException::CODE_SYSTEM_ERROR);
        }

        // 3. 从数据库读取这些角色
        $dbRoles = RolesModel::whereIn('id', $expectedIds)
            ->field('id, slug, is_system')
            ->select();
        try {
            SystemRolePolicy::assertDatabaseAnchors($roles, $dbRoles, $dbRoles->count());
        } catch (\RuntimeException $e) {
            throw ApiException::error($e->getMessage(), ApiException::CODE_SYSTEM_ERROR);
        }
        // 全部验证通过才继续。任何验证失败则零写入。

        // 5. 准备角色→能力矩阵
        $roleCaps = self::getSystemRoleCapabilityMatrix();
        $roleCaps[$roles['root']] = array_keys(RBAC::getAllCapabilities());

        // 6. 事务性写入 — commit 后清理 cache
        Db::startTrans();
        try {
            self::syncCapabilityCatalog();
            $results = [];
            foreach ($roleCaps as $roleId => $caps) {
                $inserted = 0;
                foreach (self::normalizeGrants($caps) as $grant) {
                    $exists = RoleGrants::where('role_id', $roleId)
                        ->where('capability', $grant['capability'])
                        ->where('scope', $grant['scope'])->find();
                    if (!$exists) {
                        RoleGrants::create([
                            'role_id' => $roleId,
                            'capability' => $grant['capability'],
                            'scope' => $grant['scope'],
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                        $inserted++;
                    }
                }
                $results[$roleId] = $inserted;
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw ApiException::error('能力初始化失败，已回滚', ApiException::CODE_SYSTEM_ERROR, null, $e);
        }

        // commit 成功后清理缓存
        RBAC::clearCache();
        return $results;
    }

    private static function normalizeGrants(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                $capability = (string) ($value['capability'] ?? '');
                $scope = (string) ($value['scope'] ?? '');
            } else {
                $capability = (string) $value;
                $scope = str_ends_with($capability, '.all') ? Scope::ALL : Scope::GLOBAL;
                if ($scope === Scope::ALL) $capability = substr($capability, 0, -4);
                if ($scope === Scope::GLOBAL && array_key_exists($capability . '.all', CapabilityCatalog::all())) {
                    $scope = Scope::OWN;
                }
            }
            if (!array_key_exists($capability, CapabilityCatalog::canonical())) {
                throw ApiException::badRequest('Unknown capability: ' . $capability);
            }
            Scope::assertValid($scope);
            $result[$capability . ':' . $scope] = ['capability' => $capability, 'scope' => $scope];
        }
        return array_values($result);
    }

    private static function syncCapabilityCatalog(): void
    {
        foreach (CapabilityCatalog::canonical() as $capability => $description) {
            $existing = Capabilities::where('capability', $capability)->find();
            if (!$existing) {
                Capabilities::create([
                    'capability' => $capability,
                    'description' => $description,
                    'risk_level' => 0,
                ]);
            }
        }
    }
}
