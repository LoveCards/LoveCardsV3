<?php
declare(strict_types=1);

namespace app\api
{
    class ApiException extends \RuntimeException
    {
    }
}

namespace app\modules\Content\Cards\Public
{
    use app\api\ApiException;

    final class Cards
    {
        /** @var array<int, array<string, mixed>> */
        public static array $rows = [];

        public static function get(int $id, array $caps = []): array
        {
            if (!isset(self::$rows[$id])) throw new ApiException('卡片不存在');
            return self::$rows[$id];
        }
    }
}

namespace
{
    $root = dirname(__DIR__, 2);
    require $root . '/app/shared/Resource/ResourceRef.php';
    require $root . '/app/shared/Transport/Http/CursorPage.php';
    require $root . '/app/modules/Platform/Eventing/Public/EventEnvelope.php';
    require $root . '/app/modules/Platform/Eventing/Public/DeliveryContext.php';
    require $root . '/app/modules/Platform/Eventing/Public/EventHandler.php';
    require $root . '/app/modules/Platform/Notifications/Contract/NotificationTypeCatalog.php';
    require $root . '/app/modules/Platform/Notifications/Domain/NotificationIntent.php';
    require $root . '/app/modules/Platform/Notifications/Contract/NotificationRepository.php';
    require $root . '/app/modules/Platform/Notifications/Application/ManageNotifications.php';
    require $root . '/app/modules/Platform/Notifications/Contract/NotificationPolicyStore.php';
    require $root . '/app/modules/Platform/Notifications/Application/NotificationPolicyGate.php';
    require $root . '/app/modules/Platform/Notifications/Public/Notifications.php';
    require $root . '/app/modules/Platform/Notifications/Infrastructure/ContentLikedNotificationHandler.php';
    require $root . '/app/modules/Platform/Notifications/Infrastructure/LibrarySavedNotificationHandler.php';

    use app\modules\Content\Cards\Public\Cards;
    use app\modules\Platform\Eventing\Public\DeliveryContext;
    use app\modules\Platform\Eventing\Public\EventEnvelope;
    use app\modules\Platform\Notifications\Contract\NotificationRepository;
    use app\modules\Platform\Notifications\Domain\NotificationIntent;
    use app\modules\Platform\Notifications\Infrastructure\ContentLikedNotificationHandler;
    use app\modules\Platform\Notifications\Infrastructure\LibrarySavedNotificationHandler;

    final class EngagementFakeNotificationRepository implements NotificationRepository
    {
        /** @var array<string, NotificationIntent> */
        public array $items = [];

        public function create(NotificationIntent $intent): array
        {
            $key = $intent->recipientId() . ':' . $intent->dedupeKey();
            $first = !isset($this->items[$key]);
            if ($first) $this->items[$key] = $intent;
            return [array_search($key, array_keys($this->items), true) + 1, $first, null];
        }

        public function listForUser(
            int $userId,
            ?string $cursor,
            int $limit,
            bool $unreadOnly,
            ?string $type,
            ?string $category = null
        ): array {
            return ['items' => [], 'next_cursor' => null, 'has_more' => false];
        }

        public function markRead(int $userId, array $ids): int { return 0; }
        public function markAllRead(int $userId, ?string $category = null): int { return 0; }
        public function unreadCount(int $userId, ?string $category = null): int { return 0; }
        public function recipientCanReceive(int $userId, string $type): bool { return true; }
        public function preferences(int $userId): array { return []; }
        public function savePreferences(int $userId, array $preferences): void {}
    }

    final class EngagementTestContainer
    {
        public static EngagementFakeNotificationRepository $notifications;
    }

    if (!class_exists('FakeCommunicationPorts')) {
            require_once __DIR__ . '/../../app/modules/Platform/Eventing/Public/RealtimeSignal.php';
            require_once __DIR__ . '/../../app/modules/Platform/Eventing/Public/RealtimeSignalPublisher.php';
            require_once __DIR__ . '/../../app/modules/Platform/Notifications/Contract/NotificationPolicyStore.php';
            final class FakeCommunicationPorts
            {
                private static $instances = [];
                public static function instance(string $class): object
                {
                    return self::$instances[$class] ??= new class implements
                        \app\modules\Platform\Eventing\Public\RealtimeSignalPublisher,
                        \app\modules\Platform\Notifications\Contract\NotificationPolicyStore {
                        public function publish(\app\modules\Platform\Eventing\Public\RealtimeSignal $signal): void {}
                        public function snapshot(int $policyRevision): ?array { return null; }
                        public function currentRevision(): ?int { return null; }
                        public function listCurrent(): array { return []; }
                        public function applyChanges(array $changes, int $expectedRevision, ?int $actorId, ?string $reason): array
                        { return ['revision' => $expectedRevision, 'policies' => [], 'before' => []]; }
                    };
                }
            }
        }

    function app(string $class): object
    {
        if ($class === NotificationRepository::class) return EngagementTestContainer::$notifications;
        if (in_array($class, ['app\\modules\\Platform\\Eventing\\Public\\RealtimeSignalPublisher', 'app\\modules\\Platform\\Notifications\\Contract\\NotificationPolicyStore'], true)) {
            return \FakeCommunicationPorts::instance($class);
        }
        throw new RuntimeException('Unexpected container lookup: ' . $class);
}

    /** @param array<string, mixed> $payload */
    $event = static function (
        string $type,
        string $aggregateType,
        int $aggregateId,
        array $payload,
        int $actorId,
        int $sequence
    ): EventEnvelope {
        return new EventEnvelope(
            $type,
            1,
            $aggregateType,
            (string) $aggregateId,
            $payload,
            new DateTimeImmutable('2026-08-21T00:00:00Z'),
            $actorId,
            'engagement-test-' . $sequence
        );
    };
    $context = static fn(int $eventId, string $handler): DeliveryContext =>
        new DeliveryContext($eventId, $eventId, $handler, 1);
    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };

    EngagementTestContainer::$notifications = new EngagementFakeNotificationRepository();
    $likes = new ContentLikedNotificationHandler();
    $library = new LibrarySavedNotificationHandler();

    $cardLike = $event('content.liked', 'card', 10, [
        'actor_id' => 1,
        'target_type' => 'card',
        'target_id' => 10,
        'owner_id' => 2,
        'card_id' => 10,
    ], 1, 1);
    $likes->handle($cardLike, $context(1, 'notification.content_liked'));
    $assert(count(EngagementTestContainer::$notifications->items) === 1, 'card like should create one notification');
    $cardIntent = array_values(EngagementTestContainer::$notifications->items)[0];
    $assert($cardIntent->type() === 'content.card_liked', 'card like notification type mismatch');
    $assert($cardIntent->data() === ['card_id' => 10], 'card like payload must contain card_id');

    // 模拟至少一次重复消费，以及取消点赞后再次点赞产生的新事件。
    $likes->handle($cardLike, $context(2, 'notification.content_liked'));
    $likes->handle($event('content.liked', 'card', 10, $cardLike->payload(), 1, 3), $context(3, 'notification.content_liked'));
    $assert(count(EngagementTestContainer::$notifications->items) === 1, 'duplicate or re-like must not create another notification');

    $likes->handle($event('content.liked', 'card', 11, [
        'actor_id' => 2,
        'target_type' => 'card',
        'target_id' => 11,
        'owner_id' => 2,
        'card_id' => 11,
    ], 2, 4), $context(4, 'notification.content_liked'));
    $assert(count(EngagementTestContainer::$notifications->items) === 1, 'self like must not create a notification');

    $likes->handle($event('content.liked', 'comment', 20, [
        'actor_id' => 3,
        'target_type' => 'comment',
        'target_id' => 20,
        'owner_id' => 4,
        'card_id' => 10,
        'comment_id' => 20,
    ], 3, 5), $context(5, 'notification.content_liked'));
    $assert(count(EngagementTestContainer::$notifications->items) === 2, 'comment like should create a notification');
    $commentIntent = array_values(EngagementTestContainer::$notifications->items)[1];
    $assert($commentIntent->type() === 'content.comment_liked', 'comment like notification type mismatch');
    $assert($commentIntent->data() === ['card_id' => 10, 'comment_id' => 20], 'comment like payload must contain card and comment ids');

    $saved = $event('library.saved', 'card', 10, [
        'user_id' => 5,
        'actor_id' => 5,
        'card_id' => 10,
        'owner_id' => 2,
    ], 5, 6);
    $library->handle($saved, $context(6, 'notification.library_saved'));
    $library->handle($saved, $context(7, 'notification.library_saved'));
    $assert(count(EngagementTestContainer::$notifications->items) === 3, 'duplicate save must create one notification');
    $savedIntent = array_values(EngagementTestContainer::$notifications->items)[2];
    $assert($savedIntent->type() === 'library.saved', 'saved notification should reuse library.saved type');
    $assert($savedIntent->data() === ['card_id' => 10], 'saved payload must contain card_id');

    $library->handle($event('library.saved', 'card', 12, [
        'user_id' => 2,
        'actor_id' => 2,
        'card_id' => 12,
        'owner_id' => 2,
    ], 2, 8), $context(8, 'notification.library_saved'));
    $assert(count(EngagementTestContainer::$notifications->items) === 3, 'self save must not create a notification');

    Cards::$rows = [];
    $library->handle($event('library.saved', 'card', 99, [
        'user_id' => 5,
        'actor_id' => 5,
        'card_id' => 99,
    ], 5, 9), $context(9, 'notification.library_saved'));
    $library->handle($event('library.saved', 'asset', 30, [
        'user_id' => 5,
        'actor_id' => 5,
    ], 5, 10), $context(10, 'notification.library_saved'));
    $assert(count(EngagementTestContainer::$notifications->items) === 3, 'hidden card and non-card saves must not notify');

    fwrite(STDOUT, "PASS engagement notification handlers baseline\n");

    

}
