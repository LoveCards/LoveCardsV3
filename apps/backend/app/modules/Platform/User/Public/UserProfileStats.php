<?php
declare(strict_types=1);

namespace app\modules\Platform\User\Public;

use think\facade\Db;

final class UserProfileStats
{
    public static function receivedLikes(int $userId): int
    {
        return (int) Db::table('likes')->alias('l')
            ->join('cards c', 'c.id = l.ref_id')
            ->where('l.aid', 1)
            ->where('l.ref_type', 'card')
            ->where('c.user_id', $userId)
            ->where('c.status', 0)
            ->whereNull('c.deleted_at')
            ->count();
    }
}
