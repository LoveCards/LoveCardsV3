<?php

namespace app\api\service\Rbac;

use think\facade\Db;
use app\api\model\Roles as RolesModel;
use app\api\model\RoleCapabilities;
use app\api\ApiException;
use app\common\support\ModelList;
use app\common\infra\CacheManager;

class Roles
{
    public static function Index(array $params): array
    {
        $params['search_default_key'] = 'name';
        $result = ModelList::make(RolesModel::class)->getPaginate($params);
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
            RoleCapabilities::where('role_id', 'in', $data)->delete();
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
            $validCaps = RBAC::getAllCapabilities();
            $invalidCaps = array_diff($caps, array_keys($validCaps));
            if (!empty($invalidCaps)) {
                throw ApiException::badRequest('不存在的能力：' . implode(', ', $invalidCaps));
            }

            RoleCapabilities::where('role_id', $roleId)->delete();

            foreach ($caps as $cap) {
                RoleCapabilities::create([
                    'role_id'    => $roleId,
                    'capability' => $cap,
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

        return RoleCapabilities::where('role_id', $roleId)
            ->column('capability') ?: [];
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
        $roles = config('system.system_roles');

        return [
            $roles['guest'] => [
                'cards.read',
                'comments.read',
                'tags.read',
                'files.read',
                'likes.create', 'likes.read', 'likes.delete',
            ],
            $roles['user'] => [
                'cards.read', 'cards.create',
                'comments.read', 'comments.create',
                'tags.read', 'tags.create',
                'users.read', 'users.update',
                'files.upload', 'files.read',
                'likes.create', 'likes.read', 'likes.delete',
            ],
            $roles['admin'] => [
                'cards.read', 'cards.read.all', 'cards.create',
                'cards.update.all', 'cards.delete.all',
                'cards.approve', 'cards.approve.all',
                'cards.pin.all',
                'comments.read', 'comments.read.all',
                'comments.update.all', 'comments.delete.all',
                'tags.read', 'tags.read.all', 'tags.create',
                'tags.update.all', 'tags.delete.all',
                'users.read', 'users.read.all',
                'users.update.all', 'users.delete.all',
                'files.upload', 'files.read', 'files.read.all', 'files.update.all', 'files.delete', 'files.delete.all',
                'likes.create', 'likes.read', 'likes.delete',
                'dashboard.read',
                'config.read', 'config.update', 'config.init', 'config.reload', 'config.register', 'config.deleteKey',
                'storage.read', 'storage.install', 'storage.test',
                'sender.read', 'sender.install', 'sender.test',
                'captcha.read', 'captcha.install',
                'theme.read', 'theme.update', 'theme.upload', 'theme.delete', 'theme.freeze', 'theme.activate',
                'permissions.read',
                'roles.read', 'roles.create', 'roles.update', 'roles.delete', 'roles.assign',
            ],
            $roles['root'] => [],
        ];
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
            Db::table('role_capabilities')->delete(true);

            $rows = [];
            foreach ($roleCaps as $roleId => $caps) {
                foreach ($caps as $cap) {
                    $rows[] = [
                        'role_id'    => $roleId,
                        'capability' => $cap,
                    ];
                }
            }
            RoleCapabilities::insertAll($rows);

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
        $expectedSlugs = array_keys($roles);

        // 2. 验证四个 ID 唯一
        if (count(array_unique($expectedIds)) !== 4) {
            throw ApiException::error(
                'config system_roles 包含重复 ID',
                ApiException::CODE_SYSTEM_ERROR
            );
        }

        // 3. 从数据库读取这些角色
        $dbRoles = RolesModel::whereIn('id', $expectedIds)
            ->field('id, slug, is_system')
            ->select();
        if ($dbRoles->count() !== 4) {
            throw ApiException::error(
                '数据库未包含全部 4 个系统角色（ID: ' . implode(',', $expectedIds) . '）',
                ApiException::CODE_SYSTEM_ERROR
            );
        }

        // 4. 逐行验证 ID、slug、is_system
        foreach ($dbRoles as $role) {
            $expectedSlug = array_search($role->id, $roles); // slug from config
            if ($expectedSlug === false || $role->slug !== $expectedSlug) {
                throw ApiException::error(
                    "系统角色 {$role->id} slug 为 '{$role->slug}'，期望 '{$expectedSlug}'",
                    ApiException::CODE_SYSTEM_ERROR
                );
            }
            if (!$role->is_system) {
                throw ApiException::error(
                    "系统角色 {$role->id} ({$role->slug}) is_system=0，期望 1",
                    ApiException::CODE_SYSTEM_ERROR
                );
            }
        }
        // 全部验证通过才继续。任何验证失败则零写入。

        // 5. 准备角色→能力矩阵
        $roleCaps = self::getSystemRoleCapabilityMatrix();
        $roleCaps[$roles['root']] = array_keys(RBAC::getAllCapabilities());

        // 6. 事务性写入 — commit 后清理 cache
        Db::startTrans();
        try {
            $results = [];
            foreach ($roleCaps as $roleId => $caps) {
                $inserted = 0;
                foreach ($caps as $cap) {
                    $exists = RoleCapabilities::where('role_id', $roleId)
                        ->where('capability', $cap)->find();
                    if (!$exists) {
                        RoleCapabilities::create(['role_id' => $roleId, 'capability' => $cap]);
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
}
