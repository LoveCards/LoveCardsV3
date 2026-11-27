<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

use think\facade\Db;

/** 业务模块只读取自己保存的投递引用，不依赖事件账本的内部表结构。 */
final class DeliveryProgress
{
    public static function summarize(iterable $ids): array
    {
        $result = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'dead' => 0, 'unknown' => 0, 'first_dead_event_id' => null, 'first_dead_delivery_id' => null];
        $total = 0;
        foreach (self::chunks($ids) as $chunk) {
            $total += count($chunk);
            $row = Db::table('event_deliveries')->whereIn('id', $chunk)
                ->field("COUNT(*) AS total, SUM(status = 'pending') AS pending, SUM(status = 'processing') AS processing, SUM(status = 'completed') AS completed, SUM(status = 'dead') AS dead, MIN(CASE WHEN status = 'dead' THEN id END) AS first_dead")
                ->select()->toArray()[0] ?? [];
            foreach (['pending', 'processing', 'completed', 'dead'] as $status) $result[$status] += (int) ($row[$status] ?? 0);
            $first = isset($row['first_dead']) ? (int) $row['first_dead'] : null;
            if ($first !== null && ($result['first_dead_delivery_id'] === null || $first < $result['first_dead_delivery_id'])) {
                $result['first_dead_delivery_id'] = $first;
            }
        }
        $result['unknown'] = $total - array_sum(array_intersect_key($result, array_flip(['pending', 'processing', 'completed', 'dead'])));
        if ($result['first_dead_delivery_id'] !== null) {
            $eventId = Db::table('event_deliveries')->where('id', $result['first_dead_delivery_id'])->value('event_id');
            $result['first_dead_event_id'] = $eventId !== null ? (int) $eventId : null;
        }
        return $result;
    }

    /** @return array<int,array{status:string,attempt_count:int,event_id:int}> */
    public static function statuses(array $ids): array
    {
        $result = [];
        foreach (array_chunk(self::ids($ids), 500) as $chunk) {
            $rows = Db::table('event_deliveries')->whereIn('id', $chunk)->field('id,event_id,status,attempt_count')->select()->toArray();
            foreach ($rows as $row) $result[(int) $row['id']] = ['status' => (string) $row['status'], 'attempt_count' => (int) $row['attempt_count'], 'event_id' => (int) $row['event_id']];
        }
        return $result;
    }

    private static function ids(array $ids): array
    {
        foreach ($ids as $id) if (!is_int($id) || $id < 1) throw new \InvalidArgumentException('投递引用必须为正整数');
        return array_values(array_unique($ids));
    }

    /** 流式调用方提供唯一引用；数组入口仍先去重，避免重复累计。 */
    private static function chunks(iterable $ids): iterable
    {
        if (is_array($ids)) $ids = self::ids($ids);
        $chunk = [];
        foreach ($ids as $id) {
            if (!is_int($id) || $id < 1) throw new \InvalidArgumentException('投递引用必须为正整数');
            $chunk[] = $id;
            if (count($chunk) === 500) { yield $chunk; $chunk = []; }
        }
        if ($chunk !== []) yield $chunk;
    }
}
