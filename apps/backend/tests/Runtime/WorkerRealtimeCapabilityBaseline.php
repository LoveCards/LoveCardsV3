<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/modules/Operations/Runtime/Contract/RuntimeProbe.php';
require $root . '/app/modules/Operations/Runtime/Public/WorkerState.php';
require $root . '/app/modules/Operations/Runtime/Public/WorkerHeartbeat.php';
require $root . '/app/modules/Operations/Runtime/Infrastructure/EventingWorkerProbe.php';

use app\modules\Operations\Runtime\Infrastructure\EventingWorkerProbe;
use app\modules\Operations\Runtime\Public\WorkerHeartbeat;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$hash = str_repeat('a', 64);
$row = static fn(array $overrides = []): array => array_merge([
    'worker_id' => 'eventing-1',
    'heartbeat_at' => '2026-11-10 12:00:00',
    'release_id' => 'release-1',
    'handler_catalog_hash' => $hash,
    'queue_names_json' => '["notifications","realtime"]',
], $overrides);

$assert(EventingWorkerProbe::assess([], $hash, 'release-1')['reason_code'] === 'worker-heartbeat-stale', 'no fresh worker must fail');
$assert(EventingWorkerProbe::assess([$row(['queue_names_json' => '["notifications"]'])], $hash, 'release-1')['reason_code'] === 'worker-realtime-queue-missing', 'worker without realtime queue must fail');
$assert(EventingWorkerProbe::assess([$row(['queue_names_json' => null])], $hash, 'release-1')['ready'] === false, 'legacy heartbeat without queues must fail');
$assert(EventingWorkerProbe::assess([$row(['release_id' => 'release-0'])], $hash, 'release-1')['reason_code'] === 'worker-release-mismatch', 'stale release must fail when active release is known');
$assert(EventingWorkerProbe::assess([$row(['handler_catalog_hash' => str_repeat('b', 64)])], $hash, 'release-1')['reason_code'] === 'worker-catalog-mismatch', 'stale Handler Catalog must fail');
$result = EventingWorkerProbe::assess([
    $row(['worker_id' => 'newest-but-ineligible', 'queue_names_json' => '["notifications"]']),
    $row(['worker_id' => 'eligible']),
], $hash, 'release-1');
$assert($result['ready'] === true && $result['note']['worker_id'] === 'eligible', 'another fresh capable worker may satisfy the gate');

$heartbeat = new WorkerHeartbeat('eventing-1', 'eventing', 'running', 'release-1', 'current', $hash,
    ['notifications', 'realtime', 'realtime'], 0, new DateTimeImmutable('now', new DateTimeZone('UTC')));
$assert($heartbeat->queueNames() === ['notifications', 'realtime'], 'heartbeat must report normalized actual queue selection');

echo "PASS worker realtime capability baseline\n";
