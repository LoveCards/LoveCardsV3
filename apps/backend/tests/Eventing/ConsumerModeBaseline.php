<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Platform\Eventing\Domain\DeliveryClaim;
use app\modules\Platform\Eventing\Domain\RequestExecutionBudget;
use app\modules\Platform\Eventing\Public\EventConsumptionConflict;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$reject = static function (callable $action, string $message) use ($assert): void {
    try { $action(); } catch (InvalidArgumentException) { return; }
    $assert(false, $message);
};
$now = new DateTimeImmutable('2026-11-11T00:00:00Z');
$args = ['test-worker', ['notifications'], ['request_safe'], 1, str_repeat('a', 32), $now, $now->modify('+30 seconds')];
$assert((new DeliveryClaim(...[...$args, 'scheduled', 'cli']))->triggerSource() === 'cli', 'scheduled claim keeps selected source');
$assert((new DeliveryClaim(...[...$args, 'resident']))->consumerMode() === 'resident', 'resident claim has no scheduled source');
$reject(static fn() => new DeliveryClaim(...[...$args, 'scheduled', null]), 'scheduled claim must declare cli/http source');
$reject(static fn() => new DeliveryClaim(...[...$args, 'resident', 'cli']), 'resident claim must not impersonate cli');
$reject(static fn() => new RequestExecutionBudget('scheduler-test', 1, 50, 30, ['notifications'], ['request_safe'], 'resident'), 'bounded one-shot may not impersonate resident');

$conflict = new EventConsumptionConflict('stale-revision', '配置已变化');
$assert($conflict->reasonCode() === 'stale-revision', 'mode conflict retains stable reason');

$repository = (string) file_get_contents($root . '/app/modules/Platform/Eventing/Infrastructure/ThinkDbDeliveryRepository.php');
$store = (string) file_get_contents($root . '/app/modules/Platform/Eventing/Infrastructure/ThinkDbEventConsumptionStore.php');
$scheduled = (string) file_get_contents($root . '/app/modules/Platform/Eventing/Public/ScheduledEventConsumption.php');
$middleware = (string) file_get_contents($root . '/app/middleware.php');
$assert(str_contains($repository, 'Db::startTrans()') && str_contains($repository, '->locked()') && str_contains($repository, '$claim->consumerMode()'), 'claim must check persisted mode under transaction lock');
$assert(str_contains($repository, '$claim->triggerSource()'), 'claim must enforce one selected scheduled trigger');
$assert(str_contains($store, "'draining'") && str_contains($store, "'lease_expires_at'"), 'transition must stop claims until live leases settle');
$assert(str_contains($store, "($" . "before['mode'] === 'scheduled' && $" . "trigger !== $" . "before['scheduled_trigger'])"), 'scheduled trigger change must drain old source leases');
$assert(str_contains($scheduled, "GET_LOCK('lc:eventing:scheduled'") && str_contains($scheduled, "RELEASE_LOCK('lc:eventing:scheduled'"), 'one-shot adapters share a MySQL overlap lock');
$assert(!str_contains($middleware, 'EventingRequestDrain'), 'ordinary requests must not claim event work');
$assert(!str_contains($store, 'runtime_config_state'), 'Eventing must not query Runtime private tables');
$worker = (string) file_get_contents($root . '/app/modules/Platform/Eventing/Application/WorkerLoop.php');
$assert(str_contains($worker, "['mode-not-selected', 'transition-draining']")
    && str_contains($worker, 'sleepMilliseconds'), 'resident worker idles without claiming when scheduled or draining');
$cronGuard = (string) file_get_contents($root . '/app/api/middleware/EventingCronTokenCheck.php');
$assert(!str_contains($cronGuard, '->isSsl()') && !str_contains($cronGuard, 'X_FORWARDED_PROTO')
    && str_contains($cronGuard, "strlen($" . "expected) < 32")
    && str_contains($cronGuard, "getenv('LC_EVENTING_CRON_TOKEN')"),
    'HTTP cron must require independent strong token and trusted TLS server variables');

$schema = (string) file_get_contents($root . '/database/schema.sql');
$seed = (string) file_get_contents($root . '/database/seed.sql');
$migration = (string) file_get_contents($root . '/database/migrations/20261111000001.sql');
$assert(str_contains($schema, 'CREATE TABLE `eventing_consumer_state`') && str_contains($schema, '`trigger_source` varchar(16)'), 'clean schema includes strategy and observed source');
$assert(str_contains($seed, "(1, 'scheduled', 'active', NULL, 'none'"), 'clean install does not claim cron configured');
$assert(str_contains($migration, "WHEN `desired_mode` = 'realtime' THEN 'resident'"), 'existing realtime keeps resident consumer');
$assert(str_contains($migration, "ELSE 'cli'"), 'old polling deployment requires verified CLI cron before activation');
$assert(str_contains($migration, 'information_schema.COLUMNS'), 'migration retry guards added column');
$facade = (string) file_get_contents($root . '/app/modules/Platform/Eventing/Public/EventConsumption.php');
$assert(str_contains($facade, "->order('run_id', 'desc')")
    && str_contains($facade, "['outcome'] ?? '') !== 'ok'"),
    'activation gate must inspect newest run, so a failed or stalled run overrides an older success');

fwrite(STDOUT, "PASS eventing consumer mode baseline\n");
