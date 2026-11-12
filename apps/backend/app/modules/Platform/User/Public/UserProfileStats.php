<?php
declare(strict_types=1);

namespace app\modules\Platform\User\Public;

use think\facade\Db;

final class UserProfileStats
{
    public static function receivedLikes(int $userId, bool $publicOnly = false): int
    {
        $query = Db::table('likes')->alias('l')
            ->join('cards c', 'c.id = l.ref_id')
            ->where('l.ref_type', 'card')
            ->where('c.user_id', $userId)
            ->where('c.status', 0)
            ->whereNull('c.deleted_at');
        if ($publicOnly) $query->where('c.visibility', 'public');
        return (int) $query->count();
    }

}
