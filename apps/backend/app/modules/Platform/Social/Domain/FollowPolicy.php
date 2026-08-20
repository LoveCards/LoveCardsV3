<?php

namespace app\modules\Platform\Social\Domain;

final class FollowPolicy
{
    public static function assertAllowed(int $actorId, int $targetId): void
    {
        if ($actorId <= 0 || $targetId <= 0) {
            throw new \InvalidArgumentException('用户标识无效');
        }
        if ($actorId === $targetId) {
            throw new \DomainException('不能关注自己');
        }
    }
}
