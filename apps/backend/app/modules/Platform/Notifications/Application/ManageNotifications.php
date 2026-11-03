<?php

namespace app\modules\Platform\Notifications\Application;

use app\modules\Platform\Notifications\Contract\NotificationRepository;
use app\modules\Platform\Notifications\Contract\NotificationTypeCatalog;
use app\modules\Platform\Notifications\Domain\NotificationIntent;
use app\shared\Transport\Http\CursorPage;
use app\api\ApiException;
use app\shared\Transaction\TransactionManager;

final class ManageNotifications
{
    public function __construct(private NotificationRepository $repository, private ?TransactionManager $transactions = null)
    {
    }

    public function create(NotificationIntent $intent): int
    {
        if ($intent->actorId() === $intent->recipientId()) return 0;
        if (!$this->repository->recipientCanReceive($intent->recipientId(), $intent->type())) return 0;
        return $this->repository->create($intent);
    }

    /** @return array{data: array<int, mixed>, next_cursor: ?string, has_more: bool} */
    public function list(
        int $userId,
        ?string $cursor = null,
        int $limit = 20,
        bool $unreadOnly = false,
        ?string $type = null,
        ?string $category = null
    ): array
    {
        $this->assertUser($userId);
        $type = $type === null || $type === '' ? null : NotificationTypeCatalog::normalizeType($type);
        $category = $category === null || $category === '' ? null : $category;
        if ($category !== null) NotificationTypeCatalog::typesForListCategory($category);
        $result = $this->repository->listForUser(
            $userId,
            $cursor,
            max(1, min(100, $limit)),
            $unreadOnly,
            $type,
            $category
        );
        return CursorPage::make($result['items'], $result['next_cursor'], $result['has_more']);
    }

    public function markRead(int $userId, array $ids): int
    {
        $this->assertUser($userId);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return 0;
        return $this->repository->markRead($userId, $ids);
    }

    public function markAllRead(int $userId, ?string $category = null): int
    {
        $this->assertUser($userId);
        if ($category !== null && $category !== '') NotificationTypeCatalog::typesForListCategory($category);
        return $this->repository->markAllRead($userId, $category === '' ? null : $category);
    }

    public function unreadCount(int $userId, ?string $category = null): int
    {
        $this->assertUser($userId);
        if ($category !== null && $category !== '') NotificationTypeCatalog::typesForListCategory($category);
        return $this->repository->unreadCount($userId, $category === '' ? null : $category);
    }

    /** @return array{in_app:array<string,bool>,locked_types:array<int,string>} */
    public function preferences(int $userId): array
    {
        $this->assertUser($userId);
        return ['in_app' => $this->repository->preferences($userId), 'locked_types' => NotificationTypeCatalog::lockedTypes()];
    }

    /** @param array<string,mixed> $input @return array{in_app:array<string,bool>,locked_types:array<int,string>} */
    public function updatePreferences(int $userId, array $input): array
    {
        $this->assertUser($userId);
        $inApp = $input['in_app'] ?? null;
        if (!is_array($inApp)) throw ApiException::validation(['in_app' => ['站内通知偏好必须是对象。']]);
        $allowed = array_fill_keys(NotificationTypeCatalog::configurableTypes(), true);
        $updates = [];
        foreach ($inApp as $type => $enabled) {
            if (!is_string($type) || !isset($allowed[$type])) {
                throw ApiException::validation(['in_app' => ["通知类型 {$type} 不允许配置。"]]);
            }
            if (!is_bool($enabled)) throw ApiException::validation(["in_app.{$type}" => ['必须是布尔值。']]);
            $updates[$type] = $enabled;
        }
        $save = fn() => $this->repository->savePreferences($userId, $updates);
        if ($this->transactions !== null) $this->transactions->run($save); else $save();
        return $this->preferences($userId);
    }

    private function assertUser(int $userId): void
    {
        if ($userId <= 0) throw new \InvalidArgumentException('用户标识无效');
    }
}
