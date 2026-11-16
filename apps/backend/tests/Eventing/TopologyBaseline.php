<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Platform\Eventing\Domain\DeliveryFailure;
use app\modules\Platform\Eventing\Domain\RequestExecutionBudget;
use app\modules\Platform\Eventing\Public\ExecutionMode;
use app\modules\Platform\Eventing\Public\EventHandler;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\HandlerRegistration;
use app\modules\Platform\Eventing\Public\HandlerRegistry;
use app\modules\Platform\Eventing\Public\QueueCatalog;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assertThrows = static function (callable $callback, string $message): void {
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
};

$registry = \app\EventHandlers::create();
$assert($registry->catalogHash() === '905a15da233afefb98bd8c8524b189e73846449e650e6611956be25de3de3f11', 'composition migration must preserve the signed catalog exactly');
$assert(count($registry->catalog()) === 13, 'composition must include every owning module');
$assert(QueueCatalog::names() === ['notifications', 'realtime', 'storage'], 'queue catalog must enumerate the governed queues');
$assert(QueueCatalog::definitions()['realtime']['dependencies'] === ['mysql', 'redis', 'realtime-gateway'], 'realtime queue must declare its transport dependencies');
$assertThrows(
    static fn() => QueueCatalog::assertCompatible('unknown', ExecutionMode::DAEMON_REQUIRED),
    'unregistered queues must fail before a Handler enters the catalog'
);
$assertThrows(
    static fn() => QueueCatalog::assertCompatible('realtime', ExecutionMode::REQUEST_SAFE),
    'realtime queue must reject an incompatible handler execution mode'
);
$noOpHandler = new class implements EventHandler {
    public function handle(EventEnvelope $event, DeliveryContext $context): void {}
};
$assertThrows(
    static fn() => new HandlerRegistration('queue.test', 1, 'queue.test', 'unknown', ExecutionMode::REQUEST_SAFE, $noOpHandler),
    'HandlerRegistration must enforce the governed queue catalog'
);
$assertThrows(
    static fn() => new HandlerRegistration('queue.test', 1, 'queue.test', 'storage', ExecutionMode::REQUEST_SAFE, $noOpHandler),
    'HandlerRegistration must enforce the queue execution-mode boundary'
);
$assertThrows(
    static fn() => new HandlerRegistration('queue.signal.test', 1, 'queue.signal-test', 'storage', ExecutionMode::DAEMON_REQUIRED, $noOpHandler, 5, [5], [
        'key' => 'queue.signal-test', 'topic' => 'notifications', 'owner' => 'Eventing',
        'display_name' => '测试信号', 'impact_text' => '测试接入约束', 'default_enabled' => true,
    ]),
    'signal metadata cannot bypass realtime eligibility by using another queue'
);
foreach ($registry->catalog() as $entry) {
    QueueCatalog::assertCompatible($entry['queue_name'], $entry['execution_mode']);
}
$bindings = require $root . '/app/provider.php';
$assert($bindings[HandlerRegistry::class]()->catalog() === $registry->catalog(), 'container and standalone composition must be identical');
$assert(
    $registry->queueNamesFor([ExecutionMode::REQUEST_SAFE]) === ['notifications'],
    'request-safe handler metadata must identify the short notifications queue'
);
$assert(
    $registry->queueNamesFor([ExecutionMode::DAEMON_REQUIRED]) === ['realtime', 'storage'],
    'realtime signals and slow storage work must be daemon-only'
);
$assert(
    $registry->queueNamesFor([ExecutionMode::REQUEST_SAFE, ExecutionMode::DAEMON_REQUIRED]) === ['notifications', 'realtime', 'storage'],
    'Scheduler and Worker must cover every registered queue'
);
$assertThrows(
    static fn() => $registry->queueNamesFor([]),
    'queue lookup must reject an empty execution-mode set'
);

$schedulerBudget = new RequestExecutionBudget(
    'scheduler-test',
    1,
    50,
    30,
    ['notifications', 'realtime', 'storage'],
    [ExecutionMode::REQUEST_SAFE, ExecutionMode::DAEMON_REQUIRED]
);
$assert(
    $schedulerBudget->executionModes() === [ExecutionMode::REQUEST_SAFE, ExecutionMode::DAEMON_REQUIRED],
    'bounded Scheduler executor must retain its explicit execution-mode coverage'
);
$assertThrows(
    static fn() => new RequestExecutionBudget('invalid', 1, 50, 30, []),
    'execution budgets must not silently restore a legacy queue default'
);

$failure = new DeliveryFailure(false, str_repeat('测', 1000), new DateTimeImmutable('2026-08-21T00:00:00Z'));
$assert(strlen($failure->error()) <= 2048, 'failure summary must remain within the persisted byte limit');
$assert(preg_match('//u', $failure->error()) === 1, 'failure truncation must preserve UTF-8');

$runtimeFiles = [
    $root . '/app/command/SchedulerRunOnce.php',
    $root . '/app/command/EventingWorker.php',
];
foreach ($runtimeFiles as $runtimeFile) {
    $source = file_get_contents($runtimeFile);
    $assert(is_string($source), basename($runtimeFile) . ' must exist');
    $assert(!str_contains($source, "'critical'") && !str_contains($source, "'default'") && !str_contains($source, "'bulk'"), basename($runtimeFile) . ' must not restore legacy hard-coded queues');
}
$scheduledUseCase = (string) file_get_contents($root . '/app/modules/Platform/Eventing/Public/ScheduledEventConsumption.php');
$assert(str_contains($scheduledUseCase, 'queueNamesFor'), 'scheduled consumer must derive queues from HandlerRegistry');
$assert(!str_contains((string) file_get_contents($root . '/app/middleware.php'), 'EventingRequestDrain'), 'ordinary requests must not consume event deliveries');

$migration = file_get_contents($root . '/database/migrations/20260821000006.sql');
$assert(is_string($migration) && str_contains($migration, 'VARCHAR(2048)'), 'runtime migration must align last_error width');
$assert(str_contains($migration, 'idx_delivery_claim'), 'runtime migration must index claim-token readback');
$assert(str_contains($migration, "'daemon_required'"), 'runtime migration must normalize the obsolete worker mode');

fwrite(STDOUT, "PASS eventing topology baseline\n");
