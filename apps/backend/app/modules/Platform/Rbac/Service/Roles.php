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
use app\shared\Observability\AdminMutationAudit;

class Roles
{
    public static function batchOperate(
        string $method,
        array $ids,
        int $uid,
        GrantSet $grants,
        array $actorRoleIds,
        string $ipAddress = ''
    ): void
    {
        if (!$grants->allows('roles.delete', Scope::GLOBAL)) {
            throw ApiException::forbidden('权限不足');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            throw ApiException::badRequest('no role ids supplied');
        }
        if (!in_array($method, ['delete', 'trash', 'restore', 'hard_delete'], true)) {
            throw ApiException::badRequest('不支持的操作');
        }

        Db::startTrans();
        try {
            $beforeAudit = self::auditRows($ids, true, true);
            $roles = RolesModel::withTrashed()->whereIn('id', $ids)->lock(true)->select();
            if ($roles->count() !== count($ids)) {
                throw ApiException::notFound('role not found');
            }
            foreach ($roles as $role) {
                self::assertRoleMutationAllowed($uid, $actorRoleIds, $grants, (int) $role->id);
                if ($role->is_system && in_array($method, ['delete', 'trash', 'hard_delete'], true)) {
                    throw ApiException::badRequest('系统角色不可删除');
                }
            }

            if (in_array($method, ['delete', 'trash'], true)) {
                RolesModel::whereIn('id', $ids)->delete();
            } elseif ($method === 'restore') {
                RolesModel::withTrashed()->onlyTrashed()->whereIn('id', $ids)->update(['deleted_at' => null]);
            } else {
                RoleGrants::whereIn('role_id', $ids)->delete();
                Db::table('roles')->whereIn('id', $ids)->delete();
            }
            app(AdminMutationAudit::class)->recordChanges(
                $uid,
                'admin.role.batch.' . $method,
                'role',
                $beforeAudit,
                self::auditRows($ids, true),
                $ipAddress
            );
            Db::commit();
            foreach ($ids as $roleId) RBAC::clearCache($roleId);
        } catch (\Throwable $e) {
            Db::rollback();
            if ($e instanceof ApiException) throw $e;
            throw ApiException::error('删除角色失败', ApiException::CODE_SYSTEM_ERROR, null, $e);
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

    public static function createRole(array $data, int $actorId, string $ipAddress = ''): string
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
            $roleId = (int) $result->id;
            app(AdminMutationAudit::class)->recordChanges(
                $actorId,
                'admin.role.create',
                'role',
                [],
                self::auditRows([$roleId]),
                $ipAddress
            );
            Db::commit();
            return (string) $roleId;
        } catch (\Throwable $th) {
            Db::rollback();
            if ($th instanceof ApiException) {
                throw $th;
            }
            throw ApiException::error('创建角色失败', ApiException::CODE_SYSTEM_ERROR, null, $th);
        }
    }

    public static function updateRole(
        int $id,
        array $data,
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        string $ipAddress = ''
    ): void
    {
        if (!$actorGrants->allows('roles.update', Scope::GLOBAL)) {
            throw ApiException::forbidden('权限不足');
        }
        Db::startTrans();
        try {
            $beforeAudit = self::auditRows([$id], false, true);
            $role = RolesModel::where('id', $id)->lock(true)->find();
            if (!$role) {
                throw ApiException::notFound('角色不存在');
            }

            self::assertRoleMutationAllowed($actorId, $actorRoleIds, $actorGrants, $id);

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
            app(AdminMutationAudit::class)->recordChanges(
                $actorId,
                'admin.role.update',
                'role',
                $beforeAudit,
                self::auditRows([$id]),
                $ipAddress
            );
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

    public static function deleteRoles(
        int $id,
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        string $ipAddress = ''
    ): void
    {
        if (!$actorGrants->allows('roles.delete', Scope::GLOBAL)) {
            throw ApiException::forbidden('权限不足');
        }
        $data = [$id];

        Db::startTrans();
        try {
            $beforeAudit = self::auditRows([$id], false, true);
            $role = RolesModel::where('id', $id)->lock(true)->find();
            if (!$role) throw ApiException::notFound('角色不存在');
            self::assertRoleMutationAllowed($actorId, $actorRoleIds, $actorGrants, $id);
            if ($role->is_system) {
                throw ApiException::badRequest('系统角色不可删除：' . $role->name);
            }

            RoleGrants::where('role_id', 'in', $data)->delete();
            RolesModel::destroy($data);
            app(AdminMutationAudit::class)->recordChanges(
                $actorId,
                'admin.role.delete',
                'role',
                $beforeAudit,
                self::auditRows([$id], true),
                $ipAddress
            );
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
    public static function assignCapabilities(
        int $roleId,
        array $caps,
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        string $ipAddress = ''
    ): void
    {
        if (!$actorGrants->allows('roles.assign', Scope::GLOBAL)) {
            throw ApiException::forbidden('权限不足');
        }
        Db::startTrans();
        try {
            $beforeAudit = self::auditRows([$roleId], false, true);
            $role = RolesModel::where('id', $roleId)->lock(true)->find();
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
            self::assertRoleMutationAllowed(
                $actorId,
                $actorRoleIds,
                $actorGrants,
                $roleId,
                GrantSet::fromRows($grants)
            );
            RoleGrants::where('role_id', $roleId)->delete();

            foreach ($grants as $grant) {
                RoleGrants::create([
                    'role_id' => $roleId,
                    'capability' => $grant['capability'],
                    'scope' => $grant['scope'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            app(AdminMutationAudit::class)->recordChanges(
                $actorId,
                'admin.role.assign_grants',
                'role',
                $beforeAudit,
                self::auditRows([$roleId]),
                $ipAddress
            );

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

    /** @return array<int,array<string,mixed>> */
    private static function auditRows(array $ids, bool $includeDeleted = false, bool $lock = false): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        sort($ids, SORT_NUMERIC);
        if ($ids === []) return [];
        $query = Db::table('roles')->whereIn('id', $ids)->order('id', 'asc');
        if (!$includeDeleted) $query->whereNull('deleted_at');
        if ($lock) $query->lock(true);
        $rows = $query->select()->toArray();
        $grants = Db::table('role_grants')->whereIn('role_id', $ids)
            ->field('role_id,capability,scope')
            ->order('role_id', 'asc')->order('capability', 'asc')->order('scope', 'asc')
            ->select()->toArray();
        $byRole = [];
        foreach ($grants as $grant) {
            $byRole[(int) $grant['role_id']][] = [
                'capability' => (string) $grant['capability'],
                'scope' => (string) $grant['scope'],
            ];
        }
        $result = [];
        foreach ($rows as $row) {
            $roleId = (int) $row['id'];
            $row['grants'] = $byRole[$roleId] ?? [];
            $result[$roleId] = $row;
        }
        return $result;
    }

    /** @return array<int,array<string,mixed>> */
    private static function allAuditRows(bool $lock = false): array
    {
        $query = Db::table('roles')->field('id')->order('id', 'asc');
        if ($lock) $query->lock(true);
        $ids = array_map(
            static fn(array $row): int => (int) $row['id'],
            $query->select()->toArray()
        );
        return self::auditRows($ids, true);
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
    public static function reseed(
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        string $ipAddress = ''
    ): array
    {
        if ($actorId < 1
            || !$actorGrants->allows('roles.assign', Scope::GLOBAL)
            || !SystemRolePolicy::isRootActor($actorRoleIds, config('system.system_roles'))) {
            throw ApiException::forbidden('只有超级管理员才能重新 seed 角色能力');
        }
        CacheManager::clearDomain('rbac');

        $roles = config('system.system_roles');

        Db::startTrans();
        try {
            $beforeAudit = self::allAuditRows(true);
            self::syncCapabilityCatalog();
            $templateRoleIds = self::ensureAdministrationTemplates();
            $roleCaps = self::seedCapabilityMatrix($roles, $templateRoleIds);
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

            app(AdminMutationAudit::class)->recordChanges(
                $actorId,
                'admin.role.reseed',
                'role',
                $beforeAudit,
                self::allAuditRows(),
                $ipAddress
            );

            Db::commit();
            CacheManager::clearDomain('rbac');

            return [
                'total' => count($rows),
                'guest' => count($roleCaps[$roles['guest']]),
                'user'  => count($roleCaps[$roles['user']]),
                'admin' => count($roleCaps[$roles['admin']]),
                'root'  => count($roleCaps[$roles['root']]),
                'templates' => array_map(
                    static fn (int $roleId): int => count($roleCaps[$roleId] ?? []),
                    $templateRoleIds
                ),
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

        // 5. 事务性写入 — commit 后清理 cache
        Db::startTrans();
        try {
            self::syncCapabilityCatalog();
            $templateRoleIds = self::ensureAdministrationTemplates();
            $roleCaps = self::seedCapabilityMatrix($roles, $templateRoleIds);
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
            if (!in_array($scope, Scope::all(), true)) {
                throw ApiException::badRequest("Unsupported authorization scope: {$scope}");
            }
            if (!in_array($scope, CapabilityCatalog::scopesFor($capability), true)) {
                throw ApiException::badRequest("Unsupported scope '{$scope}' for capability '{$capability}'");
            }
            $result[$capability . ':' . $scope] = ['capability' => $capability, 'scope' => $scope];
        }
        return array_values($result);
    }

    private static function assertRoleMutationAllowed(
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        int $roleId,
        ?GrantSet $proposedGrants = null
    ): void {
        $role = RolesModel::withTrashed()->where('id', $roleId)->field('id,is_system')->find();
        if ($role && (int) $role->is_system === 1
            && !SystemRolePolicy::isRootActor($actorRoleIds, config('system.system_roles'))) {
            throw ApiException::forbidden('只有超级管理员才能修改系统角色');
        }
        $violation = SystemRolePolicy::roleMutationViolation(
            $actorId,
            $actorRoleIds,
            $actorGrants,
            $roleId,
            RBAC::getFreshRoleGrants($roleId),
            $proposedGrants,
            config('system.system_roles')
        );
        if ($violation !== null) throw ApiException::forbidden($violation);
    }

    /**
     * @param array<string,int> $anchorRoles
     * @param array<string,int> $templateRoleIds
     * @return array<int,array>
     */
    private static function seedCapabilityMatrix(array $anchorRoles, array $templateRoleIds): array
    {
        $matrix = self::getSystemRoleCapabilityMatrix();
        $matrix[$anchorRoles['root']] = array_keys(RBAC::getAllCapabilities());
        foreach (SystemRolePolicy::administrationTemplates() as $slug => $template) {
            $matrix[$templateRoleIds[$slug]] = $template['grants'];
        }
        return $matrix;
    }

    /**
     * 在当前事务中创建缺失的内置管理角色，并拒绝占用保留 slug 的自定义或已删除角色。
     *
     * @return array<string,int> slug => role id
     */
    private static function ensureAdministrationTemplates(): array
    {
        $templates = SystemRolePolicy::administrationTemplates();
        $slugs = array_keys($templates);
        $existing = RolesModel::withTrashed()->whereIn('slug', $slugs)->select();
        $bySlug = [];
        foreach ($existing as $role) {
            if ((int) $role->is_system !== 1 || $role->deleted_at !== null) {
                throw ApiException::error(
                    "保留角色标识 '{$role->slug}' 已被非系统角色占用或处于删除状态",
                    ApiException::CODE_SYSTEM_ERROR
                );
            }
            $slug = (string) $role->slug;
            $template = $templates[$slug];
            if ((string) $role->name !== $template['name']
                || (string) $role->description !== $template['description']) {
                RolesModel::update([
                    'name' => $template['name'],
                    'description' => $template['description'],
                ], ['id' => (int) $role->id]);
            }
            $bySlug[$slug] = (int) $role->id;
        }

        foreach ($templates as $slug => $template) {
            if (isset($bySlug[$slug])) continue;
            $role = RolesModel::create([
                'name' => $template['name'],
                'slug' => $slug,
                'description' => $template['description'],
                'is_system' => 1,
            ]);
            $bySlug[$slug] = (int) $role->id;
        }

        return $bySlug;
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
