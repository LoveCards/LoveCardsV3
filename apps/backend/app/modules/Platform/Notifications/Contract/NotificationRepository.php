<?php

namespace app\modules\Platform\Notifications\Contract;

use app\modules\Platform\Notifications\Domain\NotificationIntent;

interface NotificationRepository
{
    /** 返回通知 ID；同一幂等键重复写入时返回已有 ID。 */
    public function create(NotificationIntent $intent): int;

    /** @return array{items: array<int, array<string, mixed>>, next_cursor: ?string, has_more: bool} */
    public function listForUser(int $userId, ?string $cursor, int $limit, bool $unreadOnly): array;

    public function markRead(int $userId, array $ids): int;
    public function markAllRead(int $userId): int;
    public function unreadCount(int $userId): int;
}
