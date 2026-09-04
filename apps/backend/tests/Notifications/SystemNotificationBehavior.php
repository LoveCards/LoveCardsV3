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
use app\modules\Platform\Notifications\Domain\SystemNotice;
use app\modules\Platform\Notifications\Public\SystemNotifications;
use app\shared\Observability\LogContext;
use app\shared\Observability\LogContextScope;
use app\shared\Transaction\TransactionManager;

final class SystemNoticeOutbox implements OutboxEventRepository
{
    /** @var array<int, EventEnvelope> */
    public array $events = [];

    public function append(EventEnvelope $event): int
    {
        $id = count($this->events) + 1;
        $this->events[$id] = $event;
        return $id;
    }

    public function findById(int $eventId): ?EventEnvelope
    {
        return $this->events[$eventId] ?? null;
    }

    /** @return array<int, EventEnvelope> */
    public function snapshot(): array { return $this->events; }

    /** @param array<int, EventEnvelope> $events */
    public function restore(array $events): void { $this->events = $events; }
}

final class SystemNoticeDeliveries implements DeliveryRepository
{
    public function __construct(private bool $failEnqueue = false)
    {
    }

    public function enqueue(int $eventId, array $definitions, \DateTimeImmutable $availableAt): array
    {
        if ($this->failEnqueue) throw new \RuntimeException('delivery enqueue failed');
        return array_map(static fn(int $index): int => $index + 1, array_keys($definitions));
    }

    public function claim(DeliveryClaim $claim): array { return []; }
    public function complete(int $deliveryId, string $claimToken, \DateTimeImmutable $completedAt): bool { return true; }
    public function fail(int $deliveryId, string $claimToken, DeliveryFailure $failure, \DateTimeImmutable $failedAt): bool { return true; }
}

final class SystemNoticeTransactions implements TransactionManager
{
    public int $commits = 0;
    public int $rollbacks = 0;

    public function __construct(private SystemNoticeOutbox $outbox)
    {
    }

    public function run(callable $callback): mixed
    {
        $before = $this->outbox->snapshot();
        try {
            $result = $callback();
            $this->commits++;
            return $result;
        } catch (\Throwable $exception) {
            $this->outbox->restore($before);
            $this->rollbacks++;
            throw $exception;
        }
    }
}

final class SystemNoticeClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-21T00:00:00Z');
    }

    public function monotonicMilliseconds(): int { return 1; }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$assertThrows = static function (callable $callback, string $expectedClass, string $message): void {
    try {
        $callback();
    } catch (Throwable $exception) {
        if ($exception instanceof $expectedClass) return;
        throw new RuntimeException($message . '; got ' . $exception::class, 0, $exception);
    }
    throw new RuntimeException($message . '; no exception thrown');
};

$outbox = new SystemNoticeOutbox();
$transactions = new SystemNoticeTransactions($outbox);
$context = new LogContextScope();
$context->set(LogContext::start('system-notice-test', 'system-notice-correlation'));
$bus = new DomainEventBus(
    new EventRecorder($outbox, new SystemNoticeDeliveries(), new HandlerRegistry(), new SystemNoticeClock()),
    $context
);
$notifications = new SystemNotifications($bus, $transactions);
$recorded = $notifications->issue(
    42,
    'security-login-20260821',
    SystemNotice::SECURITY,
    '检测到新的登录行为',
    '/settings/security?tab=sessions#active',
    'warning'
);

$assert($recorded->eventId() === 1, 'trusted API should record one outbox event');
$assert($transactions->commits === 1 && $transactions->rollbacks === 0, 'system notice should commit event and delivery together');
$event = $outbox->findById(1);
$assert($event instanceof EventEnvelope, 'trusted API should publish an event envelope');
$assert($event->eventType() === 'system.notification.issued', 'event type should be stable');
$assert($event->eventVersion() === 1, 'event version should be 1');
$assert($event->aggregateType() === 'user' && $event->aggregateKey() === '42', 'recipient should be the event aggregate');
$assert($event->actorId() === null, 'system actor should be nullable');
$assert($event->payload() === [
    'recipient_id' => 42,
    'notice_key' => 'security-login-20260821',
    'notification_type' => 'system.security',
    'payload' => [
        'message' => '检测到新的登录行为',
        'action_url' => '/settings/security?tab=sessions#active',
        'severity' => 'warning',
    ],
], 'event payload should use the frozen schema');

$projected = SystemNotice::fromEventPayload($event->payload(), $event->actorId())->notificationIntent();
$assert($projected->recipientId() === 42, 'projection should retain recipient');
$assert($projected->type() === 'system.security', 'projection should retain controlled type');
$assert($projected->actorId() === null, 'projection should retain nullable actor');
$assert($projected->resource() === null, 'system notification should not invent a resource');
$assert($projected->dedupeKey() === 'system:security-login-20260821:42', 'dedupe should combine caller notice_key and recipient');
$assert($projected->data() === [
    'message' => '检测到新的登录行为',
    'action_url' => '/settings/security?tab=sessions#active',
    'severity' => 'warning',
], 'notification payload should expose only controlled fields');

$failedOutbox = new SystemNoticeOutbox();
$failedTransactions = new SystemNoticeTransactions($failedOutbox);
$failedBus = new DomainEventBus(
    new EventRecorder($failedOutbox, new SystemNoticeDeliveries(true), new HandlerRegistry(), new SystemNoticeClock()),
    $context
);
$assertThrows(
    static fn() => (new SystemNotifications($failedBus, $failedTransactions))->issue(
        42,
        'delivery-failure-20260821',
        SystemNotice::NOTICE,
        '这条通知不得留下孤立 outbox 事件'
    ),
    RuntimeException::class,
    'delivery enqueue failure should escape the trusted API'
);
$assert($failedTransactions->commits === 0 && $failedTransactions->rollbacks === 1, 'delivery enqueue failure should roll back the transaction');
$assert($failedOutbox->events === [], 'delivery enqueue failure must not leave an outbox event without a delivery');

foreach ([SystemNotice::NOTICE, SystemNotice::SECURITY, SystemNotice::MODERATION] as $type) {
    $assert((new SystemNotice(1, 'allowed-type', $type, '消息'))->notificationIntent()->type() === $type, 'all controlled types should be accepted');
}
foreach (['info', 'success', 'warning', 'critical'] as $severity) {
    $assert((new SystemNotice(1, 'allowed-severity', SystemNotice::NOTICE, '消息', null, $severity))->notificationIntent()->data()['severity'] === $severity, 'all controlled severities should be accepted');
}

$invalidUrls = [
    'https://evil.example',
    '//evil.example/path',
    '/safe\\evil',
    "/safe\nbad",
    '/https://evil.example',
    '/%2Fevil.example',
];
foreach ($invalidUrls as $invalidUrl) {
    $assertThrows(
        static fn() => new SystemNotice(1, 'invalid-url', SystemNotice::NOTICE, '消息', $invalidUrl),
        InvalidArgumentException::class,
        'external, protocol-relative, backslash, control and encoded bypass paths should be rejected'
    );
}

$assertThrows(
    static fn() => new SystemNotice(1, 'invalid-type', 'system.custom', '消息'),
    InvalidArgumentException::class,
    'arbitrary system notification types should be rejected'
);
$assertThrows(
    static fn() => new SystemNotice(1, 'invalid-severity', SystemNotice::NOTICE, '消息', null, 'emergency'),
    InvalidArgumentException::class,
    'arbitrary severity should be rejected'
);
$assertThrows(
    static fn() => SystemNotice::fromEventPayload([
        'recipient_id' => 1,
        'notice_key' => 'extra-event-key',
        'notification_type' => SystemNotice::NOTICE,
        'payload' => ['message' => '消息', 'action_url' => null, 'severity' => 'info'],
        'recipient_ids' => [1, 2],
    ], null),
    UnexpectedValueException::class,
    'event schema should reject unauthorized targeting fields'
);
$assertThrows(
    static fn() => SystemNotice::fromEventPayload([
        'recipient_id' => 1,
        'notice_key' => 'extra-payload-key',
        'notification_type' => SystemNotice::NOTICE,
        'payload' => ['message' => '消息', 'action_url' => null, 'severity' => 'info', 'html' => '<b>消息</b>'],
    ], null),
    UnexpectedValueException::class,
    'notification payload should reject unauthorized fields'
);

fwrite(STDOUT, "PASS system notification behavior\n");
