<?php

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Platform\Notifications\Contract\NotificationRepository;
use app\modules\Platform\Notifications\Domain\NotificationIntent;
use think\facade\Db;

final class ThinkOrmNotificationRepository implements NotificationRepository
{
    public function create(NotificationIntent $intent): int
    {
        $existing = Db::table('notifications')
            ->where('recipient_id', $intent->recipientId())
            ->where('dedupe_key', $intent->dedupeKey())
            ->value('id');
        if ($existing !== null) return (int) $existing;

        $resource = $intent->resource();
        try {
            return (int) Db::table('notifications')->insertGetId([
                'recipient_id' => $intent->recipientId(),
                'dedupe_key' => $intent->dedupeKey(),
                'type' => $intent->type(),
                'actor_id' => $intent->actorId(),
                'resource_type' => $resource?->type(),
                'resource_id' => $resource?->id(),
                'payload' => json_encode($intent->data(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'read_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            $existing = Db::table('notifications')
                ->where('recipient_id', $intent->recipientId())
                ->where('dedupe_key', $intent->dedupeKey())
                ->value('id');
            if ($existing !== null) return (int) $existing;
            throw $exception;
        }
    }

    public function listForUser(int $userId, ?string $cursor, int $limit, bool $unreadOnly): array
    {
        $query = Db::table('notifications')->where('recipient_id', $userId)->order('id', 'desc')->limit($limit + 1);
        if ($cursor !== null && ctype_digit($cursor)) $query->where('id', '<', (int) $cursor);
        if ($unreadOnly) $query->whereNull('read_at');

        $rows = $query->select()->toArray();
        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);
        $next = $hasMore && $rows !== [] ? (string) end($rows)['id'] : null;
        foreach ($rows as &$row) {
            if (isset($row['payload']) && is_string($row['payload'])) {
                $decoded = json_decode($row['payload'], true);
                $row['payload'] = is_array($decoded) ? $decoded : [];
            }
        }
        unset($row);
        return ['items' => $rows, 'next_cursor' => $next, 'has_more' => $hasMore];
    }

    public function markRead(int $userId, array $ids): int
    {
        return Db::table('notifications')->where('recipient_id', $userId)->whereIn('id', $ids)->whereNull('read_at')
            ->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    public function markAllRead(int $userId): int
    {
        return Db::table('notifications')->where('recipient_id', $userId)->whereNull('read_at')
            ->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    public function unreadCount(int $userId): int
    {
        return (int) Db::table('notifications')->where('recipient_id', $userId)->whereNull('read_at')->count();
    }
}
