<?php

namespace app\modules\Platform\Notifications\Contract;

use app\modules\Platform\Notifications\Domain\NotificationIntent;

interface NotificationRepository
{
    /**
     * 返回 [通知 ID, 是否首次 insert]；同一幂等键重复写入时返回已有 ID 且首插标志为 false。
     * 首插标志供 realtime 信号判定（去重命中不发信号，契约 §4.5）。
     *
     * 第三个元素为写入时间（'Y-m-d H:i:s'，用于 realtime 游标编码）；
     * 去重命中或测试替身可返回 null。
     *
     * @return array{0: int, 1: bool, 2: ?string}
     */
    public function create(NotificationIntent $intent): array;

    public function recipientCanReceive(int $userId, string $type): bool;

    /** @return array{items: array<int, array<string, mixed>>, next_cursor: ?string, has_more: bool} */
    public function listForUser(
        int $userId,
        ?string $cursor,
        int $limit,
        bool $unreadOnly,
        ?string $type,
        ?string $category
    ): array;

    public function markRead(int $userId, array $ids): int;
    public function markAllRead(int $userId, ?string $category = null): int;
    public function unreadCount(int $userId, ?string $category = null): int;
    /** @return array<string,bool> */
    public function preferences(int $userId): array;
    /** @param array<string,bool> $preferences */
    public function savePreferences(int $userId, array $preferences): void;
}
