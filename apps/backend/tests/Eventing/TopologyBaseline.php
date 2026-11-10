<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Platform\Eventing\Domain\DeliveryFailure;
use app\modules\Platform\Eventing\Domain\RequestExecutionBudget;
use app\modules\Platform\Eventing\Public\ExecutionMode;
use app\modules\Platform\Eventing\Public\HandlerRegistry;

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

$registry = new HandlerRegistry();
$assert(
    $registry->queueNamesFor([ExecutionMode::REQUEST_SAFE]) === ['notifications'],
    'HTTP request drain must only receive the short notifications queue'
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
    $root . '/app/middleware/EventingRequestDrain.php',
    $root . '/app/command/SchedulerRunOnce.php',
    $root . '/app/command/EventingWorker.php',
];
foreach ($runtimeFiles as $runtimeFile) {
    $source = file_get_contents($runtimeFile);
    $assert(is_string($source) && str_contains($source, 'queueNamesFor'), basename($runtimeFile) . ' must derive queues from HandlerRegistry');
    $assert(!str_contains($source, "'critical'") && !str_contains($source, "'default'") && !str_contains($source, "'bulk'"), basename($runtimeFile) . ' must not restore legacy hard-coded queues');
}

$migration = file_get_contents($root . '/database/migrations/20260821000006.sql');
$assert(is_string($migration) && str_contains($migration, 'VARCHAR(2048)'), 'runtime migration must align last_error width');
$assert(str_contains($migration, 'idx_delivery_claim'), 'runtime migration must index claim-token readback');
$assert(str_contains($migration, "'daemon_required'"), 'runtime migration must normalize the obsolete worker mode');

fwrite(STDOUT, "PASS eventing topology baseline\n");
