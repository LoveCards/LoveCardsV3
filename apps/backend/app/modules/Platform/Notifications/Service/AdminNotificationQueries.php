<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Service;

use think\facade\Db;

/** 管理端只读元数据查询；不读取通知正文、用户偏好或关联资源正文。 */
final class AdminNotificationQueries
{
    /** 保持运维统计接口原有响应形状与全部存储类型口径。 */
    public function stats(): array
    {
        // ThinkORM 的无条件 find() 不执行查询；聚合必须读取 select() 的首行。
        $row = Db::table('notifications')->field('COUNT(*) AS total, SUM(read_at IS NULL) AS unread, COUNT(DISTINCT recipient_id) AS recipients')->select()->toArray()[0] ?? [];
        $types = Db::table('notifications')->field('type,COUNT(*) AS count')->group('type')->order('count', 'desc')->limit(30)->select()->toArray();
        return [
            'total' => (int) ($row['total'] ?? 0),
            'unread' => (int) ($row['unread'] ?? 0),
            'recipients' => (int) ($row['recipients'] ?? 0),
            'types' => $types,
        ];
    }
}
