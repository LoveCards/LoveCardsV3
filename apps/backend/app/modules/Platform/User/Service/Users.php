<?php

namespace app\modules\Platform\User\Service;

use app\modules\Platform\User\Infrastructure\Persistence\Model\Users as UsersModel;
use app\modules\Platform\Rbac\Infrastructure\Persistence\Model\Roles as RolesModel;
use app\modules\Platform\Rbac\Infrastructure\Persistence\Model\UserRoles;
use app\shared\Infrastructure\Persistence\BulkFieldToggle;
use app\shared\Infrastructure\Persistence\ModelQuery;
use app\shared\Authorization\GrantSet;
use app\modules\Platform\User\Service\Policy\UserPolicy;
use app\modules\Platform\Auth\Contract\SessionRegistry;
use think\facade\Db;

class Users
{

    /**
     * 批量操作
     *
     * @param string $method
     * @param array  $ids
     * @param int    $uid
     * @param array  $caps
     */
    public static function batchOperate(string $method, array $ids, int $uid, GrantSet $grants): void
    {
        if (empty($ids)) {
            throw \app\api\ApiException::badRequest('未指定要操作的资源');
        }

        $opCaps = [
            'approve' => 'users.update',
            'ban'     => 'users.update',
            'hide'    => 'users.update',
            'delete'  => 'users.delete',
            'trash'   => 'users.delete',
            'restore' => 'users.delete',
            'hard_delete' => 'users.delete',
        ];

        $cap = $opCaps[$method] ?? null;
        if (!$cap) {
            throw \app\api\ApiException::badRequest('不支持的操作');
        }

        // 能力 + 归属一体化检查
        UserPolicy::assertCanBatch($grants, substr($cap, strlen('users.')), $ids, $uid, in_array($method, ['restore', 'hard_delete'], true));

        Db::startTrans();
        try {
            match ($method) {
                'approve' => BulkFieldToggle::toggle(UsersModel::class, 'status', $ids, [0, 3], [1, 2]),
                'ban' => BulkFieldToggle::toggle(UsersModel::class, 'status', $ids, [0, 1], [2, 3]),
                'hide' => BulkFieldToggle::toggle(UsersModel::class, 'status', $ids, [0, 2], [1, 3]),
                'delete', 'trash' => UsersModel::destroy($ids),
                'restore' => UsersModel::withTrashed()->onlyTrashed()->whereIn('id', $ids)->update(['deleted_at' => null]),
                'hard_delete' => Db::table('users')->whereIn('id', $ids)->delete(),
            };
            if ($method !== 'hard_delete') {
                Db::table('users')->whereIn('id', $ids)->update(['security_version' => Db::raw('security_version + 1')]);
                foreach ($ids as $id) {
                    app(SessionRegistry::class)->revokeAllForUser((int) $id, 'admin_batch_' . $method, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                }
            }
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    public static function Index($params): array
    {
        $params['search_default_key'] = 'username';
        $params['withoutField'] = ['password'];
        $result = ModelQuery::make(UsersModel::class)->getPaginate($params);
        return $result->toArray();
    }

    public static function listAll($params, array $caps = []): array
    {
        $canReadAll = in_array('users.read.all', $caps, true);
        $result = ModelQuery::make(UsersModel::class)
            ->trashScope((int) ($params['show_deleted'] ?? 0), $canReadAll)
            ->getPaginate(array_merge($params, [
                'search_default_key' => 'username',
                'withoutField' => ['password'],
            ]))
            ->toArray();
        self::attachRoleIds($result['data']);
        return $result;
    }

    public static function Patch($id, $data): UsersModel|int
    {
        if (is_array($id)) {
            $result = UsersModel::whereIn('id', $id)->update($data);
        } else {
            $result = UsersModel::update($data, ['id' => $id]);
        }
        return $result;
    }

    /**
     * 更新用户（带归属检查）
     *
     * @param int   $id
     * @param array $data
     * @param int   $uid
     * @param array $caps
     */
    public static function updateUser(int $id, array $data, int $uid, GrantSet $grants): void
    {
        UserPolicy::assertCan($grants, 'update', $id, $uid);

        // 移除敏感字段
        unset($data['roles_id']);

        UsersModel::update($data, ['id' => $id]);
    }

    /**
     * 更新任意用户（管理员，已有 .all 能力检查由中间件完成）
     */
    public static function updateAny($id, $data, array $currentUserRoles): UsersModel|int
    {
        $roleIds = $data['roles_id'] ?? null;
        unset($data['roles_id']);
        $securityChanged = is_array($roleIds) || array_key_exists('status', $data) || (!empty($data['password']));
        if (!empty($data['password'])) {
            $data['password'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
            $data['credentials_changed_at'] = date('Y-m-d H:i:s');
        } else {
            unset($data['password']);
        }
        if ($securityChanged) $data['security_version'] = Db::raw('security_version + 1');
        if (is_array($roleIds)) {
            // 校验角色存在性
            $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
            $existingRoleIds = RolesModel::whereIn('id', $roleIds)->column('id');
            $invalidRoleIds = array_diff($roleIds, $existingRoleIds);
            if (!empty($invalidRoleIds)) {
                throw \app\api\ApiException::badRequest('角色不存在：' . implode(', ', $invalidRoleIds));
            }

            // 只有 root 才能分配 root 角色
            $rootRoleId = config('system.system_roles.root');
            if (in_array($rootRoleId, $roleIds)) {
                if (!in_array($rootRoleId, $currentUserRoles)) {
                    throw \app\api\ApiException::forbidden('只有超级管理员才能分配超级管理员角色');
                }
            }
        }

        Db::startTrans();
        try {
            $result = self::Patch($id, $data);
            if (is_array($roleIds)) {
                UserRoles::where('user_id', $id)->delete();
                $now = date('Y-m-d H:i:s');
                $rows = array_map(static fn ($roleId) => [
                    'user_id' => (int) $id,
                    'role_id' => (int) $roleId,
                    'created_at' => $now,
                ], $roleIds);
                if ($rows !== []) UserRoles::insertAll($rows);
            }
            if ($securityChanged) {
                app(SessionRegistry::class)->revokeAllForUser((int) $id, 'account_security_changed', new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            }
            Db::commit();
            return $result;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 删除用户（带归属检查）
     *
     * @param int   $id
     * @param int   $uid
     * @param array $caps
     */
    public static function deleteUser(int $id, int $uid, GrantSet $grants): void
    {
        UserPolicy::assertCan($grants, 'delete', $id, $uid);
        UsersModel::destroy($id);
    }

    /**
     * 删除任意用户（管理员）
     */
    public static function deleteAny($id): void
    {
        Db::startTrans();
        try {
            Db::table('users')->where('id', (int) $id)->update(['security_version' => Db::raw('security_version + 1')]);
            app(SessionRegistry::class)->revokeAllForUser((int) $id, 'account_deleted', new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            UsersModel::destroy($id);
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    public static function Get($id, $without = []): UsersModel
    {
        $withoutField = UsersModel::getWithoutField();
        $withoutField[] = 'password';
        $withoutField = array_merge($withoutField, $without);
        $user = UsersModel::where('id', $id)->withoutField($withoutField)->findOrEmpty();
        if (!$user->isEmpty()) {
            $user->setAttr('roles_id', UserRoles::where('user_id', (int) $id)->column('role_id') ?: []);
        }
        return $user;
    }

    private static function attachRoleIds(array &$users): void
    {
        if ($users === []) return;
        $ids = array_map(static fn ($user) => (int) $user['id'], $users);
        $rows = UserRoles::whereIn('user_id', $ids)->field('user_id,role_id')->select()->toArray();
        $byUser = [];
        foreach ($rows as $row) $byUser[(int) $row['user_id']][] = (int) $row['role_id'];
        foreach ($users as &$user) $user['roles_id'] = $byUser[(int) $user['id']] ?? [];
    }
}
