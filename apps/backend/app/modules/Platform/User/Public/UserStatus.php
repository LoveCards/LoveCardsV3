<?php

declare(strict_types=1);

namespace app\modules\Platform\User\Public;

/** 用户账号状态的唯一语义来源。 */
final class UserStatus
{
    public const ACTIVE = 0;
    public const BANNED = 1;
    public const HIDDEN = 2;
    public const PENDING = 3;

    public static function canAuthenticate(int $status): bool
    {
        return in_array($status, [self::ACTIVE, self::HIDDEN], true);
    }

    public static function isPublic(int $status): bool
    {
        return $status === self::ACTIVE;
    }

    public static function isKnown(int $status): bool
    {
        return in_array($status, [self::ACTIVE, self::BANNED, self::HIDDEN, self::PENDING], true);
    }
}
