<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Platform\Eventing\Contract\Clock;
use app\modules\Platform\Eventing\Contract\DeliveryRepository;
use app\modules\Platform\Eventing\Contract\OutboxEventRepository;
use app\modules\Platform\Eventing\Domain\DeliveryClaim;
use app\modules\Platform\Eventing\Domain\DeliveryFailure;
use app\modules\Platform\Eventing\Public\DomainEventBus;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\EventRecorder;
use app\modules\Platform\Eventing\Public\HandlerRegistry;
use app\modules\Platform\Notifications\Application\ManageNotifications;
use app\modules\Platform\Notifications\Contract\NotificationRepository;
use app\modules\Platform\Notifications\Domain\NotificationIntent;
use app\modules\Platform\Messaging\Infrastructure\NotificationMessageNotifier;
use app\shared\Observability\LogContextScope;
use app\shared\Transaction\TransactionManager;

final class RealtimeOutboxEvents implements OutboxEventRepository
{
    public array $items = [];
    public function append(EventEnvelope $event): int { $this->items[] = $event; return count($this->items); }
    public function findById(int $eventId): ?EventEnvelope { return $this->items[$eventId - 1] ?? null; }
}

final class RealtimeOutboxDeliveries implements DeliveryRepository
{
    public array $items = [];
    public bool $reject = false;
    public function enqueue(int $eventId, array $definitions, DateTimeImmutable $availableAt): array
    {
        if ($this->reject) throw new RuntimeException('enqueue failed');
        foreach ($definitions as $definition) $this->items[] = [$eventId, $definition->handlerKey()];
        return array_map(static fn(int $index): int => $index + 1, array_keys($definitions));
    }
    public function claim(DeliveryClaim $claim): array { return []; }
    public function complete(int $deliveryId, string $claimToken, DateTimeImmutable $completedAt, ?string $policyDecision = null): bool { return true; }
    public function fail(int $deliveryId, string $claimToken, DeliveryFailure $failure, DateTimeImmutable $failedAt): bool { return true; }
    public function defer(int $deliveryId, string $claimToken, DeliveryFailure $failure, DateTimeImmutable $deferredAt): bool { return true; }
    public function notePolicyDecision(int $deliveryId, string $decision): void {}
}

final class RealtimeOutboxNotifications implements NotificationRepository
{
    public array $items = [];
    public function recipientCanReceive(int $userId, string $type): bool { return true; }
    public function create(NotificationIntent $intent): array
    {
        $key = $intent->recipientId() . ':' . $intent->dedupeKey();
        if (isset($this->items[$key])) return [$this->items[$key], false, null];
        $id = count($this->items) + 1;
        $this->items[$key] = $id;
        return [$id, true, '2026-11-10 00:00:00'];
    }
    public function listForUser(int $userId, ?string $cursor, int $limit, bool $unreadOnly, ?string $type, ?string $category): array { return ['items' => [], 'next_cursor' => null, 'has_more' => false]; }
    public function markRead(int $userId, array $ids): int { return 0; }
    public function markAllRead(int $userId, ?string $category = null): int { return 0; }
    public function unreadCount(int $userId, ?string $category = null): int { return count($this->items); }
    public function preferences(int $userId): array { return []; }
    public function savePreferences(int $userId, array $preferences): void {}
}

final class RealtimeOutboxTransactions implements TransactionManager
{
    public function __construct(private RealtimeOutboxNotifications $notifications, private RealtimeOutboxEvents $events, private RealtimeOutboxDeliveries $deliveries) {}
    public function run(callable $callback): mixed
    {
        $before = [$this->notifications->items, $this->events->items, $this->deliveries->items];
        try { return $callback(); }
        catch (Throwable $exception) {
            [$this->notifications->items, $this->events->items, $this->deliveries->items] = $before;
            throw $exception;
        }
    }
}

final class RealtimeOutboxClock implements Clock
{
    public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-11-10T00:00:00Z'); }
    public function monotonicMilliseconds(): int { return 0; }
}

$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$notifications = new RealtimeOutboxNotifications();
$events = new RealtimeOutboxEvents();
$deliveries = new RealtimeOutboxDeliveries();
$transactions = new RealtimeOutboxTransactions($notifications, $events, $deliveries);
$registry = new HandlerRegistry();
$bus = new DomainEventBus(new EventRecorder($events, $deliveries, $registry, new RealtimeOutboxClock()), new LogContextScope());
$service = new ManageNotifications($notifications, $transactions, $bus);
$intent = new NotificationIntent(2, 'follow:1:2', 'social.followed', 1);

$assert($service->create($intent) === 1, 'new notification should persist');
$assert(count($events->items) === 1 && $events->items[0]->eventType() === 'notification.created', 'new notification should record an outbox event');
$assert($deliveries->items === [[1, 'realtime.notification-created']], 'new notification should enqueue one realtime delivery');
$assert($service->create($intent) === 1 && count($events->items) === 1, 'duplicate notification should not enqueue another realtime delivery');

$deliveries->reject = true;
$failed = false;
try { $service->create(new NotificationIntent(3, 'follow:1:3', 'social.followed', 1)); }
catch (RuntimeException) { $failed = true; }
$assert($failed, 'realtime delivery enqueue failure should escape the notification transaction');
$assert(count($notifications->items) === 1 && count($events->items) === 1, 'enqueue failure should roll back notification and outbox together');

$deliveries->reject = false;
$notifier = new NotificationMessageNotifier($bus);
$transactions->run(static fn() => $notifier->messageRead(2, 9, 12));
$assert($events->items[1]->eventType() === 'message.read', 'read advance should record a stable message.read event');
$assert($events->items[1]->payload() === ['reader_id' => 2, 'conversation_id' => 9, 'message_id' => 12], 'read event should not contain message content');
$assert($deliveries->items[1] === [2, 'realtime.message-read'], 'read event should enqueue a realtime delivery');

$signalCatalog = $registry->signalCatalog();
$assert(count($signalCatalog) === 3, 'realtime signals should derive from registered handlers');
$assert(array_column($signalCatalog, 'key') === ['messages.new', 'messages.read', 'notifications.new'], 'realtime signal keys should be stable and ordered');

fwrite(STDOUT, "PASS realtime outbox baseline\n");
