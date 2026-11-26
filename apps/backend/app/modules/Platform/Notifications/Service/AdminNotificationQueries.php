<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Service;

use think\facade\Db;

/** 管理端只读元数据查询；不读取通知正文、用户偏好或关联资源正文。 */
final class AdminNotificationQueries
{
    private const FIELDS = ['id', 'type', 'recipient_id', 'actor_id', 'resource_type', 'resource_id', 'read_at', 'created_at'];

    public function paginate(array $params = []): array
    {
        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['list_rows'] ?? 20);
        $query = Db::table('notifications');
        foreach (['recipient_id', 'type'] as $field) {
            if (isset($params[$field])) $query->where($field, $params[$field]);
        }
        if (($params['read_state'] ?? null) === 'read') $query->whereNotNull('read_at');
        if (($params['read_state'] ?? null) === 'unread') $query->whereNull('read_at');
        // DATETIME 无时区信息，按服务端保存原值比较；结束时间不包含在查询区间内。
        if (isset($params['from'])) $query->where('created_at', '>=', $params['from']);
        if (isset($params['to'])) $query->where('created_at', '<', $params['to']);

        $total = (int) (clone $query)->count();
        $rows = $query->field(self::FIELDS)->order('created_at', 'desc')->order('id', 'desc')
            ->page($page, $perPage)->select()->toArray();

        return [
            'data' => $rows,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function get(int $id): ?array
    {
        return Db::table('notifications')->field(self::FIELDS)->where('id', $id)->find();
    }

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
