<?php

namespace app\modules\Platform\Notifications\Application;

use app\modules\Platform\Notifications\Contract\NotificationRepository;
use app\modules\Platform\Notifications\Domain\NotificationIntent;
use app\shared\Transport\Http\CursorPage;

final class ManageNotifications
{
    public function __construct(private NotificationRepository $repository)
    {
    }

    public function create(NotificationIntent $intent): int
    {
        return $this->repository->create($intent);
    }

    /** @return array{data: array<int, mixed>, next_cursor: ?string, has_more: bool} */
    public function list(int $userId, ?string $cursor = null, int $limit = 20, bool $unreadOnly = false): array
    {
        $this->assertUser($userId);
        $result = $this->repository->listForUser($userId, $cursor, max(1, min(100, $limit)), $unreadOnly);
        return CursorPage::make($result['items'], $result['next_cursor'], $result['has_more']);
    }

    public function markRead(int $userId, array $ids): int
    {
        $this->assertUser($userId);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return 0;
        return $this->repository->markRead($userId, $ids);
    }

    public function markAllRead(int $userId): int
    {
        $this->assertUser($userId);
        return $this->repository->markAllRead($userId);
    }

    public function unreadCount(int $userId): int
    {
        $this->assertUser($userId);
        return $this->repository->unreadCount($userId);
    }

    private function assertUser(int $userId): void
    {
        if ($userId <= 0) throw new \InvalidArgumentException('用户标识无效');
    }
}
