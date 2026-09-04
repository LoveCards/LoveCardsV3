<?php

namespace app\modules\Platform\User\Service;

use app\modules\Platform\User\Infrastructure\Persistence\Model\Users as UsersModel;
use app\modules\Platform\Rbac\Infrastructure\Persistence\Model\Roles as RolesModel;
use app\modules\Platform\Rbac\Infrastructure\Persistence\Model\UserRoles;
use app\modules\Platform\Rbac\Public\AdministrationBoundary;
use app\modules\Platform\Rbac\Public\Authorization;
use app\shared\Infrastructure\Persistence\BulkFieldToggle;
use app\shared\Infrastructure\Persistence\ModelQuery;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\Scope;
use app\modules\Platform\User\Service\Policy\RootAccountPolicy;
use app\modules\Platform\User\Service\Policy\UserPolicy;
use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\modules\Platform\Notifications\Contract\SystemNoticeIssuer;
use app\modules\Platform\Notifications\Domain\SystemNotice;
use app\modules\Platform\User\Public\UserStatus;
use app\shared\Observability\AdminMutationAudit;
use think\facade\Db;

class Users
{
    /**
     * 查找可用于 @ 提及的用户候选。
     *
     * 候选搜索只暴露 username/avatar 两个字段，并在服务端统一执行账号状态、
     * 主页可见范围和双向屏蔽判断。前端不得据此推断 user_id 或通知接收人。
     *
     * @return array{total:int,per_page:int,current_page:int,last_page:int,data:array<int,array{username:string,avatar:?string}>}
     */
    public static function searchMentionCandidates(array $params, int $viewerId): array
    {
        $query = trim((string) ($params['query'] ?? ''));
        $limit = min(20, max(1, (int) ($params['limit'] ?? 8)));
        if ($viewerId < 1) {
            throw \app\api\ApiException::unauthorized('请先登入');
        }

        // Request validation owns syntax/length checks. Keep a defensive guard for
        // direct service callers so an empty search can never enumerate accounts.
        if ($query === '') return self::emptyMentionCandidates($limit);

        // Autocomplete is intentionally bounded. Usernames are short and this
        // window leaves enough room for privacy/block filtering without allowing a
        // single request to materialize an unbounded account list.
        // Escape LIKE metacharacters because `_` is a valid username character
        // but must remain a literal prefix during autocomplete.
        $likePrefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
        $rows = Db::table('users')
            ->where('status', 0)
            ->whereNull('deleted_at')
            ->where('id', '<>', $viewerId)
            ->whereRaw("username LIKE :mention_prefix ESCAPE '\\\\'", ['mention_prefix' => $likePrefix])
            ->field('id,uid,username,avatar')
            ->order('username', 'asc')
            ->order('id', 'asc')
            ->limit(200)
            ->select()
            ->toArray();
        if ($rows === []) return self::emptyMentionCandidates($limit);

        $ids = array_values(array_unique(array_filter(
            array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $rows),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === []) return self::emptyMentionCandidates($limit);

        $preferences = [];
        foreach (Db::table('privacy_preferences')->whereIn('user_id', $ids)
            ->field('user_id,profile_visibility')->select()->toArray() as $row) {
            $preferences[(int) $row['user_id']] = (string) ($row['profile_visibility'] ?? 'public');
        }
        $following = array_fill_keys(array_map('intval', Db::table('user_follows')
            ->where('follower_id', $viewerId)->whereIn('followee_id', $ids)->column('followee_id')), true);

        // A block is directional in storage, but mutual blocking must hide both
        // sides from mention discovery.
        $blocked = [];
        $relations = Db::table('user_safety_relations')
            ->where('relation', 'block')
            ->where(function ($relationQuery) use ($viewerId, $ids): void {
                $relationQuery
                    ->where(function ($outgoing) use ($viewerId, $ids): void {
                        $outgoing->where('actor_id', $viewerId)->whereIn('subject_id', $ids);
                    })
                    ->whereOr(function ($incoming) use ($viewerId, $ids): void {
                        $incoming->where('subject_id', $viewerId)->whereIn('actor_id', $ids);
                    });
            })
            ->field('actor_id,subject_id')->select()->toArray();
        foreach ($relations as $relation) {
            $actor = (int) ($relation['actor_id'] ?? 0);
            $subject = (int) ($relation['subject_id'] ?? 0);
            if ($actor === $viewerId && $subject > 0) $blocked[$subject] = true;
            if ($subject === $viewerId && $actor > 0) $blocked[$actor] = true;
        }

        $visible = [];
        $seen = [];
        $normalizedQuery = mb_strtolower($query, 'UTF-8');
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $username = trim((string) ($row['username'] ?? ''));
            if ($id < 1 || $username === '' || isset($blocked[$id])) continue;
            // whereLike follows the database collation. Re-check the canonical
            // Unicode prefix in PHP so case/collation differences cannot leak an
            // unrelated candidate.
            if (!str_starts_with(mb_strtolower($username, 'UTF-8'), $normalizedQuery)) continue;
            $key = mb_strtolower($username, 'UTF-8');
            if (isset($seen[$key])) continue;

            $visibility = $preferences[$id] ?? 'public';
            if ($visibility === 'private') continue;
            if ($visibility === 'followers' && !isset($following[$id])) continue;
            if (!in_array($visibility, ['public', 'followers'], true)) continue;

            $seen[$key] = true;
            $avatar = trim((string) ($row['avatar'] ?? ''));
            $visible[] = [
                'uid' => (string) ($row['uid'] ?? ''),
                'username' => $username,
                'avatar' => $avatar === '' ? null : $avatar,
            ];
        }

        $total = count($visible);
        $visible = array_slice($visible, 0, $limit);
        return [
            'total' => $total,
            'per_page' => $limit,
            'current_page' => 1,
            'last_page' => 1,
            'data' => $visible,
        ];
    }

    /** @return array{total:int,per_page:int,current_page:int,last_page:int,data:array<int,array{username:string,avatar:?string}>} */
    private static function emptyMentionCandidates(int $limit): array
    {
        return ['total' => 0, 'per_page' => $limit, 'current_page' => 1, 'last_page' => 1, 'data' => []];
    }


    /**
     * 批量操作
     *
     * @param string $method
     * @param array  $ids
     * @param int    $uid
     * @param array  $caps
     */
    public static function batchOperate(
        string $method,
        array $ids,
        int $uid,
        GrantSet $grants,
        array $actorRoleIds,
        string $ipAddress = ''
    ): void
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

        $ids = array_values(array_unique(array_map('intval', $ids)));

        // 能力 + 归属一体化检查
        UserPolicy::assertCanBatch($grants, substr($cap, strlen('users.')), $ids, $uid, in_array($method, ['restore', 'hard_delete'], true));

        Db::startTrans();
        try {
            $beforeAudit = self::auditRows($ids, true, true);
            foreach (self::lockedRoleIdsByUser($ids) as $targetId => $targetRoleIds) {
                self::assertUserMutationAllowed(
                    $uid,
                    $actorRoleIds,
                    $grants,
                    (int) $targetId,
                    $targetRoleIds
                );
            }
            self::assertBatchPreservesRoot($method, $ids);
            $before = self::statusById($ids, in_array($method, ['restore', 'hard_delete'], true), $method === 'restore');
            match ($method) {
                'approve' => BulkFieldToggle::toggle(UsersModel::class, 'status', $ids, [0, 3], [1, 2]),
                'ban' => BulkFieldToggle::toggle(UsersModel::class, 'status', $ids, [0, 1], [2, 3]),
                'hide' => BulkFieldToggle::toggle(UsersModel::class, 'status', $ids, [0, 2], [1, 3]),
                'delete', 'trash' => UsersModel::destroy($ids),
                'restore' => UsersModel::withTrashed()->onlyTrashed()->whereIn('id', $ids)->update(['deleted_at' => null]),
                'hard_delete' => Db::table('users')->whereIn('id', $ids)->delete(),
            };
            $afterMutation = self::auditRows($ids, true);
            $changedIds = self::changedUserIds($beforeAudit, $afterMutation);
            if ($method !== 'hard_delete') {
                if ($changedIds !== []) {
                    Db::table('users')->whereIn('id', $changedIds)->update(['security_version' => Db::raw('security_version + 1')]);
                }
                foreach ($changedIds as $id) {
                    app(SessionRegistry::class)->revokeAllForUser((int) $id, 'admin_batch_' . $method, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                }
                if (!in_array($method, ['delete', 'trash'], true)) {
                    self::publishBatchAccountNotices($method, $before, $changedIds);
                }
            }
            app(AdminMutationAudit::class)->recordChanges(
                $uid,
                'admin.user.batch.' . $method,
                'user',
                $beforeAudit,
                self::auditRows($ids, true),
                $ipAddress
            );
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
    public static function updateAny(
        int $id,
        array $data,
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        string $ipAddress = ''
    ): UsersModel|int
    {
        $roleIds = $data['roles_id'] ?? null;
        unset($data['roles_id']);
        if (is_array($roleIds) && !$actorGrants->allows('users.roles.assign', Scope::GLOBAL)) {
            throw \app\api\ApiException::forbidden('权限不足');
        }
        if ($data !== [] && !$actorGrants->allows('users.update', Scope::ALL)) {
            throw \app\api\ApiException::forbidden('权限不足');
        }

        Db::startTrans();
        try {
            $before = Db::table('users')->where('id', $id)->whereNull('deleted_at')
                ->field('id,email,phone,status,security_version')->lock(true)->find();
            if (!$before) throw \app\api\ApiException::notFound('用户不存在');

            $targetRoleIds = self::lockedRoleIdsForUser($id);
            $beforeAudit = self::auditRows([$id], false);
            $proposedGrants = null;
            if (is_array($roleIds)) {
                $roleIds = self::validateRoleIds($roleIds);
                $proposedGrants = Authorization::freshGrants($roleIds);
            }
            self::assertUserMutationAllowed(
                $actorId,
                $actorRoleIds,
                $actorGrants,
                $id,
                $targetRoleIds,
                $roleIds,
                $proposedGrants
            );

            if (array_key_exists('status', $data) && !UserStatus::isKnown((int) $data['status'])) {
                throw \app\api\ApiException::badRequest('用户状态无效');
            }
            if (in_array((int) config('system.system_roles.root'), $targetRoleIds, true)
                && (is_array($roleIds) || array_key_exists('status', $data))) {
                $changes = [$id => [
                    'has_root' => is_array($roleIds)
                        ? in_array((int) config('system.system_roles.root'), $roleIds, true)
                        : true,
                    'status' => array_key_exists('status', $data) ? (int) $data['status'] : (int) $before['status'],
                    'deleted' => false,
                ]];
                self::assertRootAccess(self::lockedRootUsers(), $changes);
            }

            $passwordChanged = !empty($data['password']);
            $contactChanged = (array_key_exists('email', $data) && (string) $data['email'] !== (string) ($before['email'] ?? ''))
                || (array_key_exists('phone', $data) && (string) $data['phone'] !== (string) ($before['phone'] ?? ''));
            $roleChanged = is_array($roleIds) && self::normalizedIds($roleIds) !== self::normalizedIds($targetRoleIds);
            $statusChanged = array_key_exists('status', $data) && (int) $data['status'] !== (int) $before['status'];
            $securityChanged = $roleChanged || $statusChanged || $passwordChanged || $contactChanged;
            if ($passwordChanged) {
                $data['password'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
                $data['credentials_changed_at'] = date('Y-m-d H:i:s');
            } else {
                unset($data['password']);
            }
            if ($securityChanged) $data['security_version'] = Db::raw('security_version + 1');

            $result = $data === [] ? 0 : self::Patch($id, $data);
            if ($roleChanged) {
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
                $after = Db::table('users')->where('id', (int) $id)->field('status,security_version')->find();
                $version = (int) ($after['security_version'] ?? 0);
                if ((int) $before['status'] !== (int) ($after['status'] ?? $before['status'])) {
                    self::publishStatusNotice((int) $id, (int) $after['status'], $version);
                }
                if ($passwordChanged) {
                    self::issueNotice((int) $id, 'user.password.changed:' . $version, SystemNotice::SECURITY, '你的账号密码已由管理员修改，所有登录会话已退出。若非预期，请尽快联系站点管理员。', 'critical');
                }
                if ($contactChanged) {
                    self::issueNotice((int) $id, 'user.contact.changed:' . $version, SystemNotice::SECURITY, '你的账号联系方式已由管理员修改，所有登录会话已退出。若非预期，请尽快联系站点管理员。', 'critical');
                }
                if ($roleChanged) {
                    self::issueNotice((int) $id, 'user.roles.changed:' . $version, SystemNotice::SECURITY, '你的账号权限已由管理员调整。', 'warning');
                }
            }
            app(AdminMutationAudit::class)->recordChanges(
                $actorId,
                'admin.user.update',
                'user',
                $beforeAudit,
                self::auditRows([$id]),
                $ipAddress
            );
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
    public static function deleteAny(
        int $id,
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        string $ipAddress = ''
    ): void
    {
        if (!$actorGrants->allows('users.delete', Scope::ALL)) {
            throw \app\api\ApiException::forbidden('权限不足');
        }
        Db::startTrans();
        try {
            $beforeAudit = self::auditRows([$id], false, true);
            $target = Db::table('users')->where('id', $id)->whereNull('deleted_at')
                ->field('id,status')->lock(true)->find();
            if (!$target) throw \app\api\ApiException::notFound('用户不存在');
            $targetRoleIds = self::lockedRoleIdsForUser($id);
            self::assertUserMutationAllowed($actorId, $actorRoleIds, $actorGrants, $id, $targetRoleIds);
            if (in_array((int) config('system.system_roles.root'), $targetRoleIds, true)) {
                self::assertRootAccess(self::lockedRootUsers(), [$id => ['deleted' => true]]);
            }

            Db::table('users')->where('id', $id)->update(['security_version' => Db::raw('security_version + 1')]);
            app(SessionRegistry::class)->revokeAllForUser($id, 'account_deleted', new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            UsersModel::destroy($id);
            app(AdminMutationAudit::class)->recordChanges(
                $actorId,
                'admin.user.delete',
                'user',
                $beforeAudit,
                self::auditRows([$id], true),
                $ipAddress
            );
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

    /** @return int[] */
    private static function validateRoleIds(array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        $existingRows = $roleIds === [] ? [] : RolesModel::whereIn('id', $roleIds)
            ->field('id')->lock(true)->select()->toArray();
        $existingRoleIds = array_map(static fn (array $row): int => (int) $row['id'], $existingRows);
        $invalidRoleIds = array_diff($roleIds, array_map('intval', $existingRoleIds));
        if ($invalidRoleIds !== []) {
            throw \app\api\ApiException::badRequest('角色不存在：' . implode(', ', $invalidRoleIds));
        }
        return $roleIds;
    }

    /** @return int[] */
    private static function lockedRoleIdsForUser(int $userId): array
    {
        $rows = Db::table('user_roles')->alias('ur')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->field('ur.role_id')->lock(true)->select()->toArray();
        return array_values(array_unique(array_map(static fn (array $row): int => (int) $row['role_id'], $rows)));
    }

    /** @return int[] */
    private static function normalizedIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    /** @return array<int,array<string,mixed>> */
    private static function auditRows(array $ids, bool $includeDeleted = false, bool $lock = false): array
    {
        $ids = self::normalizedIds(array_filter($ids, static fn($id): bool => (int) $id > 0));
        if ($ids === []) return [];
        $query = Db::table('users')->whereIn('id', $ids)->order('id', 'asc')
            ->field('id,number,username,avatar,email,phone,status,security_version,credentials_changed_at,deleted_at');
        if (!$includeDeleted) $query->whereNull('deleted_at');
        if ($lock) $query->lock(true);
        $rows = $query->select()->toArray();
        $rolesByUser = self::lockedRoleIdsByUser(array_map(static fn(array $row): int => (int) $row['id'], $rows));
        $result = [];
        foreach ($rows as $row) {
            $userId = (int) $row['id'];
            $row['roles_id'] = self::normalizedIds($rolesByUser[$userId] ?? []);
            $result[$userId] = $row;
        }
        return $result;
    }

    /** @return int[] */
    private static function changedUserIds(array $beforeById, array $afterById): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($beforeById), array_keys($afterById))) as $id) {
            $before = $beforeById[$id] ?? null;
            $after = $afterById[$id] ?? null;
            if ($before === null || $after === null
                || (int) ($before['status'] ?? -1) !== (int) ($after['status'] ?? -1)
                || ($before['deleted_at'] ?? null) !== ($after['deleted_at'] ?? null)) {
                $changed[] = (int) $id;
            }
        }
        sort($changed, SORT_NUMERIC);
        return $changed;
    }

    /** @return array<int,int[]> */
    private static function lockedRoleIdsByUser(array $userIds): array
    {
        $result = array_fill_keys(array_map('intval', $userIds), []);
        if ($result === []) return $result;
        $rows = Db::table('user_roles')->alias('ur')
            ->join('roles r', 'r.id = ur.role_id')
            ->whereIn('ur.user_id', array_keys($result))
            ->field('ur.user_id,ur.role_id')->lock(true)->select()->toArray();
        foreach ($rows as $row) $result[(int) $row['user_id']][] = (int) $row['role_id'];
        return $result;
    }

    private static function assertUserMutationAllowed(
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        int $targetUserId,
        array $targetRoleIds,
        ?array $proposedRoleIds = null,
        ?GrantSet $proposedGrants = null
    ): void {
        $violation = AdministrationBoundary::userMutationViolation(
            $actorId,
            $actorRoleIds,
            $actorGrants,
            $targetUserId,
            $targetRoleIds,
            $proposedRoleIds,
            $proposedGrants
        );
        if ($violation !== null) throw \app\api\ApiException::forbidden($violation);
    }

    /** @return array<int,array{id:int,status:int,deleted_at:mixed}> */
    private static function lockedRootUsers(): array
    {
        return Db::table('users')->alias('u')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->where('ur.role_id', (int) config('system.system_roles.root'))
            ->field('u.id,u.status,u.deleted_at')
            ->lock(true)
            ->select()
            ->toArray();
    }

    private static function assertRootAccess(array $rootUsers, array $changes): void
    {
        if (!RootAccountPolicy::preservesAccess($rootUsers, $changes)) {
            throw \app\api\ApiException::forbidden('系统必须至少保留一名可登录的超级管理员');
        }
    }

    private static function assertBatchPreservesRoot(string $method, array $ids): void
    {
        $rootUsers = self::lockedRootUsers();
        $idSet = array_fill_keys(array_map('intval', $ids), true);
        $changes = [];
        foreach ($rootUsers as $rootUser) {
            $id = (int) $rootUser['id'];
            if (!isset($idSet[$id])) continue;
            if (in_array($method, ['delete', 'trash', 'hard_delete'], true)) {
                $changes[$id] = ['deleted' => true];
            } elseif ($method === 'restore') {
                $changes[$id] = ['deleted' => false];
            } elseif (empty($rootUser['deleted_at'])) {
                $changes[$id] = ['status' => RootAccountPolicy::nextBatchStatus($method, (int) $rootUser['status'])];
            }
        }
        if ($changes !== []) self::assertRootAccess($rootUsers, $changes);
    }

    /** @param array<int, int|string> $ids @return array<int, int> */
    private static function statusById(array $ids, bool $withTrashed = false, bool $onlyTrashed = false): array
    {
        $query = $withTrashed ? UsersModel::withTrashed() : UsersModel::whereIn('id', $ids);
        if ($onlyTrashed) $query->onlyTrashed();
        if ($withTrashed) $query->whereIn('id', $ids);
        $rows = $query->field('id,status')->select()->toArray();
        $result = [];
        foreach ($rows as $row) $result[(int) $row['id']] = (int) $row['status'];
        return $result;
    }

    /** @param array<int, int> $before @param array<int, int|string> $ids */
    private static function publishBatchAccountNotices(string $method, array $before, array $ids): void
    {
        $query = UsersModel::withTrashed()->whereIn('id', $ids)->field('id,status,security_version,deleted_at');
        foreach ($query->select()->toArray() as $row) {
            $userId = (int) $row['id'];
            $version = (int) ($row['security_version'] ?? 0);
            if ($method === 'restore' && isset($before[$userId]) && empty($row['deleted_at'])) {
                self::issueNotice($userId, 'user.account.restored:' . $version, SystemNotice::MODERATION, '你的账号已由管理员恢复。', 'success');
                continue;
            }
            if (isset($before[$userId]) && $before[$userId] !== (int) $row['status']) {
                self::publishStatusNotice($userId, (int) $row['status'], $version);
            }
        }
    }

    private static function publishStatusNotice(int $userId, int $status, int $version): void
    {
        $notice = match ($status) {
            UserStatus::ACTIVE => ['你的账号已恢复正常状态。', 'success'],
            UserStatus::BANNED => ['你的账号已被管理员封禁。', 'critical'],
            UserStatus::HIDDEN => ['你的公开主页已被管理员隐藏，你仍可登录并查看账号设置。', 'warning'],
            UserStatus::PENDING => ['你的账号已进入审核状态。', 'warning'],
            default => null,
        };
        if ($notice === null) return;
        self::issueNotice($userId, 'user.status.changed:' . $version, SystemNotice::MODERATION, $notice[0], $notice[1]);
    }

    private static function issueNotice(int $userId, string $key, string $type, string $message, string $severity): void
    {
        app(SystemNoticeIssuer::class)->issue($userId, $key, $type, $message, '/settings', $severity);
    }
}
