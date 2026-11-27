<?php
declare(strict_types=1);

namespace app\modules\Platform\User\Public;

use think\facade\Db;

/** 只公开可接收系统通告的正式账号 ID 与名称，不暴露联系方式或账号私密数据。 */
final class NotificationRecipients
{
    public static function candidates(string $search, int $limit = 20): array
    {
        $search = trim($search);
        if ($search === '') return [];
        if (mb_strlen($search) > 80) throw new \InvalidArgumentException('用户搜索内容过长');
        $query = self::query();
        $plainId = ltrim($search, '#');
        if (ctype_digit($plainId) && (int) $plainId > 0) {
            $query->where('u.id', (int) $plainId);
        } else {
            $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $query->whereRaw("u.username LIKE :notice_name ESCAPE '\\\\'", ['notice_name' => $prefix]);
        }
        return array_map(self::row(...), $query->field('u.id,u.username')->order('u.id')->limit(max(1, min(20, $limit)))->select()->toArray());
    }

    /** 调用方在准备批次的事务内读取并保存结果，使确认与重试不重新解释全站范围。 */
    public static function snapshot(array $ids): array
    {
        $query = self::query();
        if ($ids === [] || count($ids) > 200) throw new \InvalidArgumentException('指定收件快照须为 1 至 200 个用户');
        $query->whereIn('u.id', $ids);
        return array_map(self::row(...), $query->field('u.id,u.username')->order('u.id')->select()->toArray());
    }

    /** 按稳定主键流式读取同一事务快照；每次最多装配 500 个 ID。 */
    public static function snapshotIdChunks(): iterable
    {
        $upper = (int) self::query()->max('u.id');
        $after = 0;
        while ($after < $upper) {
            $ids = array_map('intval', self::query()->where('u.id', '>', $after)->where('u.id', '<=', $upper)
                ->order('u.id')->limit(500)->column('u.id'));
            if ($ids === []) break;
            yield $ids;
            $after = $ids[count($ids) - 1];
        }
    }

    public static function eligible(int $userId): bool
    {
        return self::query()->where('u.id', $userId)->count() === 1;
    }

    private static function query(): mixed
    {
        $guestRole = (int) config('system.system_roles.guest');
        return Db::table('users')->alias('u')->whereNull('u.deleted_at')->whereIn('u.status', [UserStatus::ACTIVE, UserStatus::BANNED, UserStatus::HIDDEN, UserStatus::PENDING])
            ->whereRaw('NOT EXISTS (SELECT 1 FROM user_roles notice_guest WHERE notice_guest.user_id = u.id AND notice_guest.role_id = :notice_guest_role)', ['notice_guest_role' => $guestRole]);
    }

    private static function row(array $row): array
    {
        return ['id' => (int) $row['id'], 'username' => (string) $row['username']];
    }
}
