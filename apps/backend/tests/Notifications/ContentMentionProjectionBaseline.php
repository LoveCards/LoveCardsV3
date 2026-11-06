<?php

declare(strict_types=1);

namespace think\facade {
    final class Db
    {
        /** @var array<int, true> */
        public static array $activeUsers = [];
        public static function table(string $table): object
        {
            if ($table !== 'users') throw new \RuntimeException('Unexpected table: ' . $table);
            return new class {
                private array $ids = [];
                public function whereIn(string $field, array $ids): self { $this->ids = array_map('intval', $ids); return $this; }
                public function where(...$args): self { return $this; }
                public function whereNull(string $field): self { return $this; }
                public function column(string $field): array
                {
                    return array_values(array_filter($this->ids, static fn(int $id): bool => isset(Db::$activeUsers[$id])));
                }
            };
        }
    }
}

namespace {
    $root = dirname(__DIR__, 2);
    require $root . '/app/shared/Resource/ResourceRef.php';
    require $root . '/app/shared/Transport/Http/CursorPage.php';
    require $root . '/app/modules/Platform/Eventing/Public/EventEnvelope.php';
    require $root . '/app/modules/Platform/Eventing/Public/DeliveryContext.php';
    require $root . '/app/modules/Platform/Eventing/Public/EventHandler.php';
    require $root . '/app/modules/Platform/Notifications/Domain/NotificationIntent.php';
    require $root . '/app/modules/Platform/Notifications/Contract/NotificationRepository.php';
    require $root . '/app/modules/Platform/Notifications/Contract/NotificationTypeCatalog.php';
    require $root . '/app/modules/Platform/Notifications/Application/ManageNotifications.php';
    require $root . '/app/modules/Platform/Notifications/Contract/NotificationPolicyStore.php';
    require $root . '/app/modules/Platform/Notifications/Application/NotificationPolicyGate.php';
    require $root . '/app/modules/Platform/Notifications/Public/Notifications.php';
    require $root . '/app/modules/Platform/Notifications/Infrastructure/ContentCommentPublishedNotificationHandler.php';
    require $root . '/app/modules/Platform/Notifications/Infrastructure/ContentMentionsAddedNotificationHandler.php';

    use app\modules\Platform\Eventing\Public\DeliveryContext;
    use app\modules\Platform\Eventing\Public\EventEnvelope;
    use app\modules\Platform\Notifications\Contract\NotificationRepository;
    use app\modules\Platform\Notifications\Domain\NotificationIntent;
    use app\modules\Platform\Notifications\Infrastructure\ContentCommentPublishedNotificationHandler;
    use app\modules\Platform\Notifications\Infrastructure\ContentMentionsAddedNotificationHandler;
    use think\facade\Db;

    final class ContentMentionFakeRepository implements NotificationRepository
    {
        /** @var array<string, NotificationIntent> */
        public array $items = [];
        public function recipientCanReceive(int $userId, string $type): bool { return isset(Db::$activeUsers[$userId]); }
        public function create(NotificationIntent $intent): array
        {
            $key = $intent->recipientId() . ':' . $intent->dedupeKey();
            $first = !isset($this->items[$key]);
            if ($first) $this->items[$key] = $intent;
            return [array_search($key, array_keys($this->items), true) + 1, $first, null];
        }
        public function listForUser(int $userId, ?string $cursor, int $limit, bool $unreadOnly, ?string $type, ?string $category): array { return ['items' => [], 'next_cursor' => null, 'has_more' => false]; }
        public function markRead(int $userId, array $ids): int { return 0; }
        public function markAllRead(int $userId, ?string $category = null): int { return 0; }
        public function unreadCount(int $userId, ?string $category = null): int { return 0; }
        public function preferences(int $userId): array { return []; }
        public function savePreferences(int $userId, array $preferences): void {}
    }

    final class ContentMentionContainer { public static ContentMentionFakeRepository $repository; }
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
        if ($class === NotificationRepository::class) return ContentMentionContainer::$repository;
        if (in_array($class, ['app\\modules\\Platform\\Eventing\\Public\\RealtimeSignalPublisher', 'app\\modules\\Platform\\Notifications\\Contract\\NotificationPolicyStore'], true)) {
            return \FakeCommunicationPorts::instance($class);
        }
        throw new RuntimeException('Unexpected container lookup: ' . $class);
}

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };
    $event = static fn(string $type, int $id, array $payload, int $actor, int $sequence): EventEnvelope => new EventEnvelope(
        $type,
        1,
        $type === 'content.comment.published' ? 'comment' : (string) ($payload['resource_type'] ?? 'card'),
        (string) $id,
        $payload,
        new DateTimeImmutable('2026-08-21T00:00:00Z'),
        $actor,
        'content-mention-test-' . $sequence
    );
    $context = static fn(int $id, string $key): DeliveryContext => new DeliveryContext($id, $id, $key, 1);

    Db::$activeUsers = [1 => true, 2 => true, 3 => true, 4 => true]; // user 5 is inactive/deleted
    ContentMentionContainer::$repository = new ContentMentionFakeRepository();
    $comments = new ContentCommentPublishedNotificationHandler();
    $mentions = new ContentMentionsAddedNotificationHandler();

    $reply = $event('content.comment.published', 90, [
        'actor_id' => 1,
        'card_id' => 10,
        'comment_id' => 90,
        'parent_comment_id' => 80,
        'card_author_id' => 3,
        'parent_author_id' => 3,
        'mentions' => [
            ['user_id' => 3, 'username' => 'parent_user'],
            ['user_id' => 4, 'username' => 'mentioned_user'],
            ['user_id' => 5, 'username' => 'deleted_user'],
            ['user_id' => 4, 'username' => 'mentioned_user'],
        ],
    ], 1, 1);
    $comments->handle($reply, $context(1, 'notifications.content-comment-published'));
    $comments->handle($reply, $context(2, 'notifications.content-comment-published'));

    $items = array_values(ContentMentionContainer::$repository->items);
    $assert(count($items) === 2, 'reply plus one eligible mention should be projected exactly once under duplicate consumption');
    $assert($items[0]->type() === 'content.replied' && $items[0]->recipientId() === 3, 'parent/card owner should receive the single most-specific reply notification');
    $assert($items[1]->type() === 'content.mentioned' && $items[1]->recipientId() === 4, 'other active mentioned user should receive a mention');
    $assert($items[1]->data() === ['card_id' => 10, 'comment_id' => 90, 'mentioned_username' => 'mentioned_user'], 'comment mention payload should be stable');

    $cardMention = $event('content.mentions.added', 11, [
        'resource_type' => 'card',
        'resource_id' => 11,
        'card_id' => 11,
        'comment_id' => null,
        'actor_id' => 1,
        'mentions' => [
            ['user_id' => 1, 'username' => 'self_user'],
            ['user_id' => 4, 'username' => 'mentioned_user'],
            ['user_id' => 5, 'username' => 'deleted_user'],
        ],
        'suppressed_recipient_ids' => [],
    ], 1, 3);
    $mentions->handle($cardMention, $context(3, 'notifications.content-mentions-added'));
    $mentions->handle($cardMention, $context(4, 'notifications.content-mentions-added'));
    $items = array_values(ContentMentionContainer::$repository->items);
    $assert(count($items) === 3, 'self, inactive and duplicate mention deliveries must be suppressed');
    $assert($items[2]->dedupeKey() === 'mention:card:11:user:4', 'mention should use a durable business dedupe key');

    fwrite(STDOUT, "PASS content comment and mention notification projection\n");

    

}
