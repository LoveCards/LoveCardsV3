<?php

namespace app\modules\Platform\Notifications\Public;

use app\modules\Platform\Eventing\Public\DomainEventBus;
use app\modules\Platform\Notifications\Application\ManageNotifications;
use app\modules\Platform\Notifications\Application\NotificationPolicyGate;
use app\modules\Platform\Notifications\Contract\NotificationPolicyStore;
use app\modules\Platform\Notifications\Contract\NotificationRepository;
use app\modules\Platform\Notifications\Domain\NotificationIntent;
use app\shared\Resource\ResourceRef;
use app\shared\Transaction\TransactionManager;

final class Notifications
{
    private static function useCase(): ManageNotifications
    {
        return new ManageNotifications(
            app(NotificationRepository::class),
            app(TransactionManager::class),
            app(DomainEventBus::class),
            new NotificationPolicyGate(app(NotificationPolicyStore::class))
        );
    }
    public static function create(NotificationIntent $intent, ?array $policyScope = null): int { return self::useCase()->create($intent, $policyScope); }
    public static function direct(int $recipientId, string $dedupeKey, string $type, ?int $actorId = null, ?ResourceRef $resource = null, array $data = [], ?array $policyScope = null): int
    {
        return self::create(new NotificationIntent($recipientId, $dedupeKey, $type, $actorId, $resource, $data), $policyScope);
    }
    public static function list(int $userId, ?string $cursor = null, int $limit = 20, bool $unreadOnly = false, ?string $type = null, ?string $category = null): array { return self::useCase()->list($userId, $cursor, $limit, $unreadOnly, $type, $category); }
    public static function markRead(int $userId, array $ids): int { return self::useCase()->markRead($userId, $ids); }
    public static function markAllRead(int $userId, ?string $category = null): int { return self::useCase()->markAllRead($userId, $category); }
    public static function unreadCount(int $userId, ?string $category = null): int { return self::useCase()->unreadCount($userId, $category); }
    public static function preferences(int $userId): array { return self::useCase()->preferences($userId); }
    public static function updatePreferences(int $userId, array $input): array { return self::useCase()->updatePreferences($userId, $input); }
}
