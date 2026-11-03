<?php

namespace app\modules\Platform\Rbac\Domain;

use app\shared\Authorization\GrantSet;

final class SystemRolePolicy
{
    /**
     * 内置管理角色模板。
     *
     * slug 是跨安装稳定标识，数据库 ID 由安装现场分配，任何授权判断都不能依赖模板 ID。
     * 模板只定义默认最小 grant；超级管理员后续显式增加的 grant 不会被非破坏性种子删除。
     *
     * @return array<string,array{name:string,description:string,grants:array<int,array{capability:string,scope:string}>}>
     */
    public static function administrationTemplates(): array
    {
        return [
            'content_operator' => [
                'name' => '内容运营',
                'description' => '负责卡片与标签的日常内容治理，不包含审核、用户或系统权限。',
                'grants' => self::grants([
                    ['admin.access', 'global'], ['dashboard.read', 'global'],
                    ['cards.read', 'all'], ['cards.create', 'global'],
                    ['cards.update', 'all'], ['cards.delete', 'all'],
                    ['tags.read', 'all'], ['tags.create', 'global'],
                    ['tags.update', 'all'], ['tags.delete', 'all'],
                    ['files.upload', 'global'],
                ]),
            ],
            'community_operator' => [
                'name' => '社区运营',
                'description' => '负责评论修正、置顶、举报和社区聚合信息，不包含卡片编辑或系统配置。',
                'grants' => self::grants([
                    ['admin.access', 'global'], ['dashboard.read', 'global'],
                    ['comments.read', 'all'], ['comments.update', 'all'], ['comments.pin', 'all'], ['comments.delete', 'all'],
                    ['reports.read', 'global'], ['reports.manage', 'global'],
                    ['community.insights.read', 'global'],
                ]),
            ],
            'moderator' => [
                'name' => '审核员',
                'description' => '处理卡片、评论和举报审核，不获得正文编辑、删除或系统权限。',
                'grants' => self::grants([
                    ['admin.access', 'global'], ['dashboard.read', 'global'],
                    ['cards.read', 'all'], ['cards.approve', 'all'],
                    ['comments.read', 'all'], ['comments.moderate', 'all'],
                    ['reports.read', 'global'], ['reports.manage', 'global'],
                ]),
            ],
            'security_admin' => [
                'name' => '安全管理员',
                'description' => '处理账号状态、会话和恢复事件，并读取安全相关审计记录。',
                'grants' => self::grants([
                    ['admin.access', 'global'], ['dashboard.read', 'global'],
                    ['users.read', 'all'], ['users.update', 'all'],
                    ['users.security.read', 'global'], ['users.sessions.revoke', 'global'],
                    ['users.recovery.read', 'global'], ['audit.read', 'global'],
                ]),
            ],
            'operations_admin' => [
                'name' => '运维管理员',
                'description' => '负责基础设施配置、事件投递、升级迁移和运行恢复。',
                'grants' => self::grants([
                    ['admin.access', 'global'], ['dashboard.read', 'global'],
                    ['operations.read', 'global'], ['operations.migrate', 'global'],
                    ['operations.retry', 'global'], ['communications.read', 'global'],
                    ['audit.read', 'global'],
                    ['system.update', 'global'], ['system.update.contract', 'global'],
                    ['system.recover', 'global'],
                    ['config.read', 'global'], ['config.update', 'global'],
                    ['storage.read', 'global'], ['storage.install', 'global'], ['storage.test', 'global'],
                    ['sender.read', 'global'], ['sender.install', 'global'], ['sender.test', 'global'],
                    ['captcha.read', 'global'], ['captcha.install', 'global'],
                    ['files.read', 'all'], ['files.update', 'all'], ['files.delete', 'all'],
                    ['files.restore', 'all'], ['files.purge', 'all'],
                    ['files.cleanup', 'global'], ['files.usage.read', 'global'],
                ]),
            ],
            'auditor' => [
                'name' => '审计员',
                'description' => '只读核对获授权的内容、用户、配置摘要、运行状态和审计记录。',
                'grants' => self::grants([
                    ['admin.access', 'global'], ['dashboard.read', 'global'],
                    ['cards.read', 'all'], ['comments.read', 'all'], ['tags.read', 'all'],
                    ['reports.read', 'global'], ['community.insights.read', 'global'],
                    ['users.read', 'all'], ['users.security.read', 'global'], ['users.recovery.read', 'global'],
                    ['config.read', 'global'], ['storage.read', 'global'], ['sender.read', 'global'],
                    ['captcha.read', 'global'], ['theme.read', 'global'],
                    ['files.read', 'all'], ['files.usage.read', 'global'],
                    ['operations.read', 'global'], ['communications.read', 'global'], ['audit.read', 'global'],
                ]),
            ],
        ];
    }

    /**
     * 系统角色默认能力矩阵。
     *
     * @param array $roles config('system.system_roles')
     * @return array role_id => string[]
     */
    public static function capabilityMatrix(array $roles): array
    {
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
                'cards.update', 'cards.delete',
                'comments.read', 'comments.create',
                'comments.pin',
                'comments.delete',
                'tags.read', 'tags.create',
                'tags.update', 'tags.delete',
                'users.read', 'users.update',
                'files.upload', 'files.read', 'files.delete',
                'likes.create', 'likes.read', 'likes.delete',
            ],
            $roles['admin'] => [
                'admin.access',
                'cards.read', 'cards.read.all', 'cards.create',
                'cards.update.all', 'cards.delete.all',
                'cards.approve', 'cards.approve.all',
                'cards.pin.all',
                'comments.read', 'comments.read.all',
                'comments.update.all', 'comments.moderate.all', 'comments.pin.all', 'comments.delete.all',
                'tags.read', 'tags.read.all', 'tags.create',
                'tags.update.all', 'tags.delete.all',
                'users.read', 'users.read.all',
                'users.update.all', 'users.delete.all', 'users.roles.assign',
                'files.upload', 'files.read', 'files.read.all', 'files.update.all', 'files.delete', 'files.delete.all',
                'files.restore', 'files.restore.all', 'files.purge.all', 'files.cleanup',
                'files.usage.read',
                'likes.create', 'likes.read', 'likes.delete',
                'dashboard.read',
                'system.update', 'system.update.contract', 'system.recover',
                'config.read', 'config.update', 'config.init', 'config.reload', 'config.register', 'config.deleteKey',
                'storage.read', 'storage.install', 'storage.test',
                'sender.read', 'sender.install', 'sender.test',
                'captcha.read', 'captcha.install',
                'theme.read', 'theme.update', 'theme.upload', 'theme.delete', 'theme.activate',
                'permissions.read',
                'roles.read', 'roles.create', 'roles.update', 'roles.delete', 'roles.assign',
                'operations.read', 'operations.migrate', 'operations.retry', 'audit.read',
                'reports.read', 'reports.manage', 'users.security.read', 'users.sessions.revoke',
                'users.recovery.read', 'communications.read', 'community.insights.read',
            ],
            $roles['root'] => [],
        ];
    }

    public static function assertConfigAnchors(array $roles): void
    {
        if (count(array_unique(array_values($roles))) !== 4) {
            throw new \RuntimeException('config system_roles 包含重复 ID');
        }
    }

    public static function isRootActor(array $actorRoleIds, array $roles): bool
    {
        return in_array((int) $roles['root'], array_map('intval', $actorRoleIds), true);
    }

    public static function grantsWithin(GrantSet $ceiling, GrantSet $candidate): bool
    {
        foreach ($candidate->toArray() as $grant) {
            if (!$ceiling->allows((string) $grant['capability'], (string) $grant['scope'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * 返回角色变更违反的垂直授权边界；null 表示允许。
     *
     * 非 root 不能修改 root、自己正在使用的角色、或能力高于自己的角色，
     * 也不能给目标角色写入自己不拥有的 grant。
     */
    public static function roleMutationViolation(
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        int $targetRoleId,
        GrantSet $currentRoleGrants,
        ?GrantSet $proposedRoleGrants,
        array $roles
    ): ?string {
        if ($actorId < 1) return '操作者身份无效';
        if (self::isRootActor($actorRoleIds, $roles)) return null;
        if ($targetRoleId === (int) $roles['root']) return '只有超级管理员才能修改 root 角色';
        if (in_array($targetRoleId, array_map('intval', $actorRoleIds), true)) {
            return '不能修改自己正在使用的角色';
        }
        if (!self::grantsWithin($actorGrants, $currentRoleGrants)) {
            return '不能修改权限层级高于自己的角色';
        }
        if ($proposedRoleGrants !== null && !self::grantsWithin($actorGrants, $proposedRoleGrants)) {
            return '不能分配自己不拥有的能力';
        }
        return null;
    }

    /**
     * 返回用户管理违反的垂直授权边界；null 表示允许。
     *
     * $proposedRoleIds 为 null 表示本次不变更角色。
     * 专职安全动作可关闭不相关 grant 的目标上限比较，但 root 保护始终生效。
     */
    public static function userMutationViolation(
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        int $targetUserId,
        array $targetRoleIds,
        GrantSet $targetGrants,
        ?array $proposedRoleIds,
        ?GrantSet $proposedGrants,
        array $roles,
        bool $enforceTargetGrantCeiling = true
    ): ?string {
        if ($actorId < 1) return '操作者身份无效';
        if (self::isRootActor($actorRoleIds, $roles)) return null;

        $targetRoleIds = array_map('intval', $targetRoleIds);
        if (in_array((int) $roles['root'], $targetRoleIds, true)) {
            return '只有超级管理员才能管理 root 用户';
        }
        if ($enforceTargetGrantCeiling && !self::grantsWithin($actorGrants, $targetGrants)) {
            return '不能管理权限层级高于自己的用户';
        }
        if ($proposedRoleIds === null) return null;
        if ($actorId === $targetUserId) return '不能通过管理接口修改自己的角色';

        $proposedRoleIds = array_map('intval', $proposedRoleIds);
        if (in_array((int) $roles['root'], $proposedRoleIds, true)) {
            return '只有超级管理员才能分配 root 角色';
        }
        if ($proposedGrants === null || !self::grantsWithin($actorGrants, $proposedGrants)) {
            return '不能分配权限层级高于自己的角色';
        }
        return null;
    }

    /**
     * @param iterable $dbRoles RolesModel 集合或等价行对象列表
     */
    public static function assertDatabaseAnchors(array $roles, iterable $dbRoles, int $count): void
    {
        if ($count !== 4) {
            throw new \RuntimeException('数据库未包含全部 4 个系统角色（ID: ' . implode(',', array_values($roles)) . '）');
        }

        foreach ($dbRoles as $role) {
            $expectedSlug = array_search($role->id, $roles);
            if ($expectedSlug === false || $role->slug !== $expectedSlug) {
                throw new \RuntimeException("系统角色 {$role->id} slug 为 '{$role->slug}'，期望 '{$expectedSlug}'");
            }
            if (!$role->is_system) {
                throw new \RuntimeException("系统角色 {$role->id} ({$role->slug}) is_system=0，期望 1");
            }
        }
    }

    /** @param array<int,array{0:string,1:string}> $values */
    private static function grants(array $values): array
    {
        return array_map(
            static fn (array $grant): array => ['capability' => $grant[0], 'scope' => $grant[1]],
            $values
        );
    }
}
