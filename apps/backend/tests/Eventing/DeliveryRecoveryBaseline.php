<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Platform\Eventing\Application\DeliveryProcessor;
use app\modules\Platform\Eventing\Contract\Clock;
use app\modules\Platform\Eventing\Contract\DeliveryRepository;
use app\modules\Platform\Eventing\Contract\FailureSanitizer;
use app\modules\Platform\Eventing\Contract\OutboxEventRepository;
use app\modules\Platform\Eventing\Domain\ClaimedDelivery;
use app\modules\Platform\Eventing\Domain\DeliveryClaim;
use app\modules\Platform\Eventing\Domain\DeliveryDefinition;
use app\modules\Platform\Eventing\Domain\DeliveryFailure;
use app\modules\Platform\Eventing\Domain\DeliveryOutcome;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\EventHandler;
use app\modules\Platform\Eventing\Public\ExecutionMode;
use app\modules\Platform\Eventing\Public\HandlerRegistration;
use app\modules\Platform\Eventing\Public\HandlerRegistry;
use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\SignalTransportUnavailable;
use app\shared\Observability\LogContextScope;

final class RecoveryOutbox implements OutboxEventRepository
{
    public function __construct(private EventEnvelope $event) {}
    public function append(EventEnvelope $event): int { return 1; }
    public function findById(int $eventId): ?EventEnvelope { return $eventId === 1 ? $this->event : null; }
}

final class RecoveryDeliveries implements DeliveryRepository
{
    public ?DeliveryFailure $failure = null;
    public bool $completed = false;
    public bool $deferred = false;
    public function enqueue(int $eventId, array $definitions, DateTimeImmutable $availableAt): array { return []; }
    public function claim(DeliveryClaim $claim): array { return []; }
    public function complete(int $deliveryId, string $claimToken, DateTimeImmutable $completedAt, ?string $policyDecision = null): bool { $this->completed = true; $this->completedDecision = $policyDecision; return true; }
    public ?string $completedDecision = null;
    public function fail(int $deliveryId, string $claimToken, DeliveryFailure $failure, DateTimeImmutable $failedAt): bool { $this->failure = $failure; return true; }
    public function defer(int $deliveryId, string $claimToken, DeliveryFailure $failure, DateTimeImmutable $deferredAt): bool { $this->failure = $failure; $this->deferred = true; return true; }
    public function notePolicyDecision(int $deliveryId, string $decision): void { $this->policyDecisions[] = [$deliveryId, $decision]; }
    public array $policyDecisions = [];
}

final class RecoveryClock implements Clock
{
    public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-21T00:00:00Z'); }
    public function monotonicMilliseconds(): int { return 0; }
}

final class RecoverySanitizer implements FailureSanitizer
{
    public function sanitize(Throwable $exception): string { return $exception->getMessage(); }
}

final class RecoveryHandler implements EventHandler
{
    public int $calls = 0;
    public function handle(EventEnvelope $event, DeliveryContext $context): void { $this->calls++; }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$handler = new RecoveryHandler();
$registry = new HandlerRegistry();
$reflection = new ReflectionClass($registry);
foreach (['byKey', 'byEvent'] as $propertyName) {
    $property = $reflection->getProperty($propertyName);
    $property->setAccessible(true);
    $property->setValue($registry, []);
}
$registry->register(new HandlerRegistration(
    'eventing.recovery.test',
    1,
    'eventing.recovery-test',
    'notifications',
    ExecutionMode::REQUEST_SAFE,
    $handler,
    2
));

$event = new EventEnvelope(
    'eventing.recovery.test',
    1,
    'test',
    '1',
    [],
    new DateTimeImmutable('2026-08-21T00:00:00Z'),
    null,
    'eventing-recovery-baseline'
);
$deliveries = new RecoveryDeliveries();
$processor = new DeliveryProcessor(
    new RecoveryOutbox($event),
    $deliveries,
    $registry,
    new RecoveryClock(),
    new RecoverySanitizer(),
    new LogContextScope()
);
$outcome = $processor->process(new ClaimedDelivery(
    1,
    1,
    'eventing.recovery-test',
    'notifications',
    ExecutionMode::REQUEST_SAFE,
    3,
    str_repeat('a', 32),
    new DateTimeImmutable('2026-08-21T00:05:00Z')
));

$assert($outcome === DeliveryOutcome::DEAD, 'an expired lease reclaimed beyond max attempts must become dead');
$assert($handler->calls === 0, 'a poison delivery must not execute its handler again');
$assert($deliveries->failure?->terminal() === true, 'reclaimed poison delivery must use terminal failure state');
$assert($deliveries->completed === false, 'reclaimed poison delivery must not be marked completed');

$transportHandler = new class implements EventHandler {
    public function handle(EventEnvelope $event, DeliveryContext $context): void
    {
        throw new SignalTransportUnavailable('gateway unavailable');
    }
};
$transportRegistry = new HandlerRegistry();
$reflection = new ReflectionClass($transportRegistry);
foreach (['byKey', 'byEvent'] as $propertyName) {
    $property = $reflection->getProperty($propertyName);
    $property->setAccessible(true);
    $property->setValue($transportRegistry, []);
}
$transportRegistry->register(new HandlerRegistration(
    'eventing.recovery.test', 1, 'eventing.recovery-test', 'realtime',
    ExecutionMode::DAEMON_REQUIRED, $transportHandler, 2
));
$transportDeliveries = new RecoveryDeliveries();
$transportProcessor = new DeliveryProcessor(
    new RecoveryOutbox($event), $transportDeliveries, $transportRegistry,
    new RecoveryClock(), new RecoverySanitizer(), new LogContextScope()
);
$transportOutcome = $transportProcessor->process(new ClaimedDelivery(
    2, 1, 'eventing.recovery-test', 'realtime', ExecutionMode::DAEMON_REQUIRED,
    1, str_repeat('b', 32), new DateTimeImmutable('2026-08-21T00:05:00Z')
));
$assert($transportOutcome === DeliveryOutcome::RETRY_SCHEDULED, 'transport outage should defer delivery');
$assert($transportDeliveries->deferred && $transportDeliveries->failure?->terminal() === false, 'transport outage must use defer instead of business failure');
$assert($transportDeliveries->failure?->availableAt() > new DateTimeImmutable('2026-08-21T00:00:00Z'), 'deferred delivery must wait before retry');

foreach (['skipped', 'applied'] as $decision) {
    $signalHandler = new class($decision) implements EventHandler {
        public function __construct(private string $decision) {}
        public function handle(EventEnvelope $event, DeliveryContext $context): void { $context->markSignalDecision($this->decision); }
    };
    $signalRegistry = new HandlerRegistry();
    $signalRegistry->register(new HandlerRegistration(
        'eventing.recovery.test', 1, 'test.signal-decision', 'realtime',
        ExecutionMode::DAEMON_REQUIRED, $signalHandler, 2, [5],
        ['key' => 'test.signal-decision', 'topic' => 'messages', 'owner' => 'Eventing',
            'display_name' => '测试信号', 'impact_text' => '验证决策持久化', 'default_enabled' => true]
    ));
    $signalDeliveries = new RecoveryDeliveries();
    $signalProcessor = new DeliveryProcessor(
        new RecoveryOutbox($event), $signalDeliveries, $signalRegistry,
        new RecoveryClock(), new RecoverySanitizer(), new LogContextScope()
    );
    $signalOutcome = $signalProcessor->process(new ClaimedDelivery(
        3, 1, 'test.signal-decision', 'realtime', ExecutionMode::DAEMON_REQUIRED,
        1, str_repeat('c', 32), new DateTimeImmutable('2026-08-21T00:05:00Z')
    ));
    $assert($signalOutcome === DeliveryOutcome::COMPLETED && $signalDeliveries->completedDecision === $decision,
        'successful realtime signal must complete with its ' . $decision . ' decision');
}

$missingDecisionHandler = new class implements EventHandler {
    public function handle(EventEnvelope $event, DeliveryContext $context): void {}
};
$missingDecisionRegistry = new HandlerRegistry();
$missingDecisionRegistry->register(new HandlerRegistration(
    'eventing.recovery.test', 1, 'test.missing-decision', 'realtime',
    ExecutionMode::DAEMON_REQUIRED, $missingDecisionHandler, 2, [5],
    ['key' => 'test.missing-decision', 'topic' => 'messages', 'owner' => 'Eventing',
        'display_name' => '测试信号', 'impact_text' => '验证缺失决策拒绝', 'default_enabled' => true]
));
$missingDecisionDeliveries = new RecoveryDeliveries();
$missingDecisionOutcome = (new DeliveryProcessor(
    new RecoveryOutbox($event), $missingDecisionDeliveries, $missingDecisionRegistry,
    new RecoveryClock(), new RecoverySanitizer(), new LogContextScope()
))->process(new ClaimedDelivery(
    4, 1, 'test.missing-decision', 'realtime', ExecutionMode::DAEMON_REQUIRED,
    1, str_repeat('d', 32), new DateTimeImmutable('2026-08-21T00:05:00Z')
));
$assert($missingDecisionOutcome === DeliveryOutcome::RETRY_SCHEDULED && !$missingDecisionDeliveries->completed,
    'realtime handler without a decision must not pretend success');

fwrite(STDOUT, "PASS eventing delivery recovery baseline\n");
