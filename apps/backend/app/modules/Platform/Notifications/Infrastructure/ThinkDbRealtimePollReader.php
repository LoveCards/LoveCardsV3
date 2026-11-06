<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Platform\Notifications\Contract\RealtimePollReader;
use app\modules\Platform\Notifications\Domain\NotificationCursor;
use think\facade\Db;

/**
 * 按游标统计“更新的通知”：游标语义与列表接口相反方向（向更新侧前进），
 * 供 Gateway 在 Redis 信号缺失时兜底判断是否有新数据。
 */
final class ThinkDbRealtimePollReader implements RealtimePollReader
{
    public function since(int $uid, ?string $cursor, int $limit): array
    {
        if ($uid < 1) {
            return ['latest_cursor' => null, 'newer' => 0];
        }
        $query = Db::table('notifications')->where('recipient_id', $uid);

        $cursor = $cursor === null ? '' : trim($cursor);
        if ($cursor !== '') {
            if (ctype_digit($cursor)) {
                // 旧版数字游标：按 id 前进。
                $query->where('id', '>', (int) $cursor);
            } else {
                $anchor = NotificationCursor::decode($cursor);
                if ($anchor === null) {
                    return ['latest_cursor' => null, 'newer' => 0];
                }
                $query->where(function ($q) use ($anchor): void {
                    $q->where('created_at', '>', $anchor['created_at'])
                        ->whereOr(function ($same) use ($anchor): void {
                            $same->where('created_at', $anchor['created_at'])->where('id', '>', $anchor['id']);
                        });
                });
            }
        }

        $rows = $query->field('id,created_at')
            ->order('created_at', 'asc')
            ->order('id', 'asc')
            ->limit(max(1, min(200, $limit)))
            ->select()
            ->toArray();
        if ($rows === []) {
            return ['latest_cursor' => $cursor === '' ? null : $cursor, 'newer' => 0];
        }
        $newest = $rows[count($rows) - 1];
        return [
            'latest_cursor' => NotificationCursor::encode((string) $newest['created_at'], (int) $newest['id']),
            'newer' => count($rows),
        ];
    }
}
