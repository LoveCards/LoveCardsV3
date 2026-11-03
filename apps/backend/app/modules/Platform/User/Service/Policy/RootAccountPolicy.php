<?php

namespace app\modules\Platform\User\Service\Policy;

use app\modules\Platform\User\Public\UserStatus;

/** 保证管理操作结束后仍至少有一名可登录的 root 用户。 */
final class RootAccountPolicy
{
    /**
     * @param array<int,array{id:int,status:int,deleted_at:mixed}> $rootUsers
     * @param array<int,array{has_root?:bool,status?:int,deleted?:bool}> $changes 按 user id 索引
     */
    public static function preservesAccess(array $rootUsers, array $changes): bool
    {
        $seen = [];
        foreach ($rootUsers as $user) {
            $id = (int) $user['id'];
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $change = $changes[$id] ?? [];
            $hasRoot = (bool) ($change['has_root'] ?? true);
            $status = (int) ($change['status'] ?? $user['status']);
            $deleted = (bool) ($change['deleted'] ?? !empty($user['deleted_at']));
            if ($hasRoot && !$deleted && UserStatus::canAuthenticate($status)) return true;
        }
        return false;
    }

    public static function nextBatchStatus(string $method, int $status): int
    {
        return match ($method) {
            'approve' => match ($status) {
                UserStatus::ACTIVE => UserStatus::PENDING,
                UserStatus::PENDING => UserStatus::ACTIVE,
                default => UserStatus::PENDING,
            },
            'ban' => match ($status) {
                UserStatus::ACTIVE => UserStatus::BANNED,
                UserStatus::BANNED => UserStatus::ACTIVE,
                default => UserStatus::BANNED,
            },
            'hide' => match ($status) {
                UserStatus::ACTIVE => UserStatus::HIDDEN,
                UserStatus::HIDDEN => UserStatus::ACTIVE,
                default => UserStatus::HIDDEN,
            },
            default => $status,
        };
    }
}
