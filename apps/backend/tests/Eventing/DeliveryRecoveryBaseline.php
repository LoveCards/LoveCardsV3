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
    public function enqueue(int $eventId, array $definitions, DateTimeImmutable $availableAt): array { return []; }
    public function claim(DeliveryClaim $claim): array { return []; }
    public function complete(int $deliveryId, string $claimToken, DateTimeImmutable $completedAt): bool { $this->completed = true; return true; }
    public function fail(int $deliveryId, string $claimToken, DeliveryFailure $failure, DateTimeImmutable $failedAt): bool { $this->failure = $failure; return true; }
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

fwrite(STDOUT, "PASS eventing delivery recovery baseline\n");
