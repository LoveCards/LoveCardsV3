<?php

namespace app\modules\Platform\Rbac\Domain;

final class SystemRolePolicy
{
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

    public static function assertConfigAnchors(array $roles): void
    {
        if (count(array_unique(array_values($roles))) !== 4) {
            throw new \RuntimeException('config system_roles 包含重复 ID');
        }
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
}
