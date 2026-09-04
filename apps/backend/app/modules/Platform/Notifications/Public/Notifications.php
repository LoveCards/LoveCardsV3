<?php

namespace app\modules\Platform\Notifications\Public;

use app\modules\Platform\Notifications\Application\ManageNotifications;
use app\modules\Platform\Notifications\Contract\NotificationRepository;
use app\modules\Platform\Notifications\Domain\NotificationIntent;
use app\shared\Resource\ResourceRef;

final class Notifications
{
    private static function useCase(): ManageNotifications { return new ManageNotifications(app(NotificationRepository::class)); }
    public static function create(NotificationIntent $intent): int { return self::useCase()->create($intent); }
    public static function direct(int $recipientId, string $dedupeKey, string $type, ?int $actorId = null, ?ResourceRef $resource = null, array $data = []): int
    {
        return self::create(new NotificationIntent($recipientId, $dedupeKey, $type, $actorId, $resource, $data));
    }
    public static function list(int $userId, ?string $cursor = null, int $limit = 20, bool $unreadOnly = false, ?string $type = null, ?string $category = null): array { return self::useCase()->list($userId, $cursor, $limit, $unreadOnly, $type, $category); }
    public static function markRead(int $userId, array $ids): int { return self::useCase()->markRead($userId, $ids); }
    public static function markAllRead(int $userId, ?string $category = null): int { return self::useCase()->markAllRead($userId, $category); }
    public static function unreadCount(int $userId, ?string $category = null): int { return self::useCase()->unreadCount($userId, $category); }
    public static function preferences(int $userId): array { return self::useCase()->preferences($userId); }
    public static function updatePreferences(int $userId, array $input): array { return self::useCase()->updatePreferences($userId, $input); }
}
