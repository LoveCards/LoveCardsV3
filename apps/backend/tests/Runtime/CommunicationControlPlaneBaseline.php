<?php
declare(strict_types=1);

// ============================================================
// LoveCardsV3 — Communication Runtime Control Plane Baseline
//
// Independent PHP script, no PHPUnit, no real database.
// 冻结通信运行时控制面行为：默认 polling/ready、实时故障不回退、
// CAS 乐观并发、白名单校验、业务模块禁止感知通信模式。
//
// Usage: @php tests/Runtime/CommunicationControlPlaneBaseline.php
// Exit code: 0 = PASS, 1 = FAIL
// ============================================================

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\api\ApiException;
use app\modules\Operations\Runtime\Application\ResolveRuntimeCommunication;
use app\modules\Operations\Runtime\Application\UpdateRuntimeConfig;
use app\modules\Operations\Runtime\Contract\RuntimeClock;
use app\modules\Operations\Runtime\Contract\RuntimeConfigStore;
use app\modules\Operations\Runtime\Contract\RuntimeProbe;
use app\modules\Operations\Runtime\Domain\CommunicationSettings;

$errors = [];
$passCount = 0;
$failCount = 0;

function pass(string $msg): void { global $passCount; $passCount++; echo "[PASS] {$msg}\n"; }
function fail(string $msg): void { global $failCount, $errors; $failCount++; $errors[] = $msg; echo "[FAIL] {$msg}\n"; }
function assertTrue(bool $cond, string $msg): void { $cond ? pass($msg) : fail($msg); }

final class FakeClock implements RuntimeClock
{
    public function nowUtc(): \DateTimeImmutable { return new \DateTimeImmutable('2026-11-06T00:00:00', new \DateTimeZone('UTC')); }
}

final class FakeStore implements RuntimeConfigStore
{
    public ?array $row;
    public array $audits = [];
    public int $saveCalls = 0;
    public bool $readFails = false;

    public function __construct(?array $row = [])
    {
        // [] = 默认单例行；null = 配置表不可读（不能用 ??，会把 null 吞成默认）。
        if ($row === []) {
            $row = [
                'desired_mode' => 'polling',
                'settings' => CommunicationSettings::defaults(),
                'revision' => 1,
                'last_apply_state' => 'applied',
                'last_apply_reason' => null,
            ];
        }
        $this->row = $row;
    }

    public function current(): ?array
    {
        if ($this->readFails) throw new \RuntimeException('simulated storage failure');
        return $this->row;
    }
    public function selectedModeForEnqueue(): ?string { return $this->row['desired_mode'] ?? null; }

    public function compareAndSwap(int $expectedRevision, string $desiredMode, array $settings, ?int $actorId, ?callable $then = null, ?callable $beforeUpdate = null): bool
    {
        $this->saveCalls++;
        if ($this->row === null || (int) $this->row['revision'] !== $expectedRevision) return false;
        if ($beforeUpdate !== null) $beforeUpdate();
        $this->row['desired_mode'] = $desiredMode;
        $this->row['settings'] = $settings;
        $this->row['revision'] = $expectedRevision + 1;
        if ($then !== null) $then();
        return true;
    }

    public function recordApply(string $state, ?string $reason): void
    {
        if ($this->row !== null) {
            $this->row['last_apply_state'] = $state;
            $this->row['last_apply_reason'] = $reason;
        }
    }
}

final class NamedProbe implements RuntimeProbe
{
    public function __construct(private string $key, private $ready, private ?string $reason = null) {}
    public function key(): string { return $this->key; }
    public function probe(array $settings): array { return ['ready' => $this->ready, 'provider' => 'fake', 'reason_code' => $this->reason]; }
}

function probes(bool $workerReady = true, bool $gatewayReady = true, bool $redisReady = true): array
{
    return [
        new NamedProbe('event-consumer', true, null),
        new NamedProbe('realtime-redis', $redisReady, $redisReady ? null : 'redis-unreachable'),
        new NamedProbe('event-worker', $workerReady, $workerReady ? null : 'worker-heartbeat-stale'),
        new NamedProbe('realtime-gateway', $gatewayReady, $gatewayReady ? null : 'gateway-not-configured'),
        new NamedProbe('scheduler', true, null),
    ];
}

function makeResolver(RuntimeConfigStore $store, array $probeList): ResolveRuntimeCommunication
{
    return new ResolveRuntimeCommunication($store, new FakeClock(), $probeList);
}

// ----------------------------------------------------------------
// 1. Default install: desired=polling -> ready even when worker/gateway are down.
// ----------------------------------------------------------------
$snapshot = makeResolver(new FakeStore(), probes(false, false))->snapshot();
assertTrue($snapshot['desired_mode'] === 'polling', 'default desired is polling');
assertTrue($snapshot['effective_mode'] === 'polling' && $snapshot['status'] === 'ready', 'polling stays ready without worker/gateway (acceptance #1)');
assertTrue($snapshot['reason_code'] === null, 'polling/ready has no reason code');
assertTrue(count($snapshot['capabilities']) === 5, 'five capabilities listed');

// ----------------------------------------------------------------
// 2. desired=realtime with missing capabilities -> degraded, preserving selected mode.
// ----------------------------------------------------------------
$realtimeRow = ['desired_mode' => 'realtime', 'settings' => CommunicationSettings::defaults(), 'revision' => 2, 'last_apply_state' => 'applied', 'last_apply_reason' => null];
$snapshot = makeResolver(new FakeStore($realtimeRow), probes(true, false))->snapshot();
assertTrue($snapshot['effective_mode'] === 'realtime' && $snapshot['status'] === 'degraded', 'realtime fault does not switch transport');
assertTrue($snapshot['reason_code'] === 'gateway-not-configured', 'degraded snapshot carries gateway reason');

$snapshot = makeResolver(new FakeStore($realtimeRow), probes(false, true))->snapshot();
assertTrue($snapshot['reason_code'] === 'worker-heartbeat-stale', 'worker blocks realtime before gateway');

$snapshot = makeResolver(new FakeStore($realtimeRow), probes(true, true, false))->snapshot();
assertTrue($snapshot['effective_mode'] === 'realtime' && $snapshot['reason_code'] === 'redis-unreachable', 'redis outage pauses realtime without polling fallback');

$snapshot = makeResolver(new FakeStore($realtimeRow), probes(true, true))->snapshot();
assertTrue($snapshot['effective_mode'] === 'realtime' && $snapshot['status'] === 'ready', 'realtime takes effect only when all capabilities ready');

// ----------------------------------------------------------------
// 3. 运行期配置不可读或损坏不能伪造默认模式。
// ----------------------------------------------------------------
$invalidStores = [
    'missing row' => new FakeStore(null),
    'invalid mode' => new FakeStore(array_replace($realtimeRow, ['desired_mode' => 'unknown'])),
    'invalid settings' => new FakeStore(array_replace($realtimeRow, ['settings' => ['notification_interval_seconds' => 0]])),
    'invalid revision' => new FakeStore(array_replace($realtimeRow, ['revision' => 0])),
    'read failure' => new FakeStore($realtimeRow),
];
$invalidStores['read failure']->readFails = true;
foreach ($invalidStores as $case => $invalidStore) {
    $caught = null;
    try { makeResolver($invalidStore, probes(true, true))->snapshot(); }
    catch (\Throwable $exception) { $caught = $exception; }
    assertTrue($caught instanceof ApiException && $caught->getHttpStatus() === 503
        && ($caught->getData()['reason_code'] ?? null) === 'runtime-config-unavailable',
        "{$case} reports unavailable without a fabricated polling snapshot");
}

// ----------------------------------------------------------------
// 4. Settings whitelist and ranges.
// ----------------------------------------------------------------
$violations = CommunicationSettings::validateInput(['desired_mode' => 'realtime', 'expected_revision' => 1, 'notification_interval_seconds' => 2]);
assertTrue(count($violations) === 1 && $violations[0]['field'] === 'notification_interval_seconds', 'interval below 5s rejected (acceptance #5)');
$violations = CommunicationSettings::validateInput(['desired_mode' => 'websocket', 'expected_revision' => 0, 'unknown_key' => 1]);
assertTrue(count($violations) === 3, 'invalid mode, bad revision and unknown key all rejected');
$violations = CommunicationSettings::validateInput(['desired_mode' => 'polling', 'expected_revision' => 4, 'pause_when_hidden' => 'yes']);
assertTrue(count($violations) === 1 && $violations[0]['field'] === 'pause_when_hidden', 'non-boolean rejected');
$normalized = CommunicationSettings::normalize(['notification_interval_seconds' => '15', 'legacy_mode' => 'sse']);
assertTrue($normalized['notification_interval_seconds'] === 15, 'normalize casts numeric strings');
assertTrue(!array_key_exists('legacy_mode', $normalized), 'unknown stored keys dropped');

// ----------------------------------------------------------------
// 5. Apply: CAS success path writes audit through the same-transaction sink.
// ----------------------------------------------------------------
$store = new FakeStore();
$resolver = makeResolver($store, probes(true, true));
$updater = new UpdateRuntimeConfig($store, $resolver, static fn (): bool => true);
$audited = 0;
$result = $updater->apply(7, ['desired_mode' => 'realtime', 'expected_revision' => 1, 'notification_interval_seconds' => 20],
    static function (int $uid, array $payload) use (&$audited): void { $audited++; });
assertTrue($audited === 1, 'apply records exactly one audit entry');
assertTrue($store->row['revision'] === 2 && $store->row['desired_mode'] === 'realtime', 'CAS bumps revision and stores desired');
assertTrue($store->row['settings']['notification_interval_seconds'] === 20, 'provided settings merged');
assertTrue($store->row['settings']['message_interval_seconds'] === CommunicationSettings::defaults()['message_interval_seconds'], 'unprovided settings preserved');
assertTrue($result['effective_mode'] === 'realtime' && $result['status'] === 'ready', 'apply realtime only after readiness passes');

// 5a. 开启门禁拒绝不完整的实时链路，不修改所选模式。
$blockedStore = new FakeStore();
$blockedUpdater = new UpdateRuntimeConfig($blockedStore, makeResolver($blockedStore, probes(true, false)), static fn (): bool => true);
$blocked = null;
try {
    $blockedUpdater->apply(7, ['desired_mode' => 'realtime', 'expected_revision' => 1], static function (): void {});
} catch (ApiException $exception) { $blocked = $exception; }
assertTrue(($blocked?->getData()['reason_code'] ?? null) === 'gateway-not-configured', 'gateway failure blocks enabling realtime');
assertTrue($blockedStore->row['desired_mode'] === 'polling' && $blockedStore->saveCalls === 0, 'rejected enable preserves polling');
assertTrue($blockedUpdater->validate(['desired_mode' => 'realtime', 'expected_revision' => 1])['valid'] === false, 'validation reports missing realtime dependency');

// 6. CAS conflict -> 409/9016 stale-revision, row untouched (acceptance #3).
$before = $store->row;
$caught = null;
try {
    $updater->apply(7, ['desired_mode' => 'polling', 'expected_revision' => 1], static function (): void { $GLOBALS['should_not_run'] = true; });
} catch (ApiException $exception) { $caught = $exception; }
assertTrue($caught !== null && $caught->getCode() === ApiException::CODE_CONFIG_CONFLICT, 'stale revision raises config conflict');
assertTrue(($caught?->getData()['reason_code'] ?? null) === 'stale-revision', 'conflict carries stale-revision reason code');
assertTrue($store->row['revision'] === 2 && $store->row['desired_mode'] === 'realtime', 'conflict leaves persisted state unchanged');

// 7. Validation rejection path: 422 + last_apply snapshot, no CAS write.
$caught = null;
$saveBefore = $store->saveCalls;
try {
    $updater->apply(7, ['desired_mode' => 'polling', 'expected_revision' => 2, 'worker_stale_seconds' => 99999], static function (): void {});
} catch (ApiException $exception) { $caught = $exception; }
assertTrue($caught !== null && $caught->getCode() === ApiException::CODE_VALIDATION_ERROR, 'out-of-range parameter raises 422 validation');
assertTrue($store->saveCalls === $saveBefore, 'validation failure performs no CAS write');
assertTrue(($store->row['last_apply_state'] ?? null) === 'rejected', 'rejected apply recorded for UI feedback');

// 8. Architecture guard: business modules must not branch on communication mode.
$offenders = [];
foreach (['Platform', 'Content', 'Theme'] as $family) {
    $dir = $root . '/app/modules/' . $family;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') continue;
        $source = (string) file_get_contents($file->getPathname());
        foreach (['desired_mode', 'effective_mode'] as $needle) {
            if (str_contains($source, $needle)) $offenders[] = $file->getPathname() . ':' . $needle;
        }
    }
}
assertTrue($offenders === [], 'no communication-mode awareness inside business modules (acceptance #6)' . ($offenders ? ' -> ' . implode(', ', array_slice($offenders, 0, 5)) : ''));

// 9. 持久策略行必须在通信 CAS 事务内阻止 scheduled + realtime。
$guardedStore = new FakeStore();
$guardedUpdater = new UpdateRuntimeConfig($guardedStore, makeResolver($guardedStore, probes()), static fn (): bool => false);
$guardError = null;
try { $guardedUpdater->apply(7, ['desired_mode' => 'realtime', 'expected_revision' => 1], static function (): void {}); }
catch (ApiException $exception) { $guardError = $exception; }
assertTrue(($guardError?->getData()['reason_code'] ?? null) === 'event-consumer-not-resident', 'transactional event mode gate blocks realtime');
assertTrue($guardedStore->row['desired_mode'] === 'polling', 'blocked transactional gate preserves polling');

// 10. 持久化适配器不能把损坏 JSON 或缺失选择转换成空默认设置。
$database = new class {
    public ?array $row = null;
    public bool $readFails = false;
    public function table(string $table): self { return $this; }
    public function where(string $field, $value): self { return $this; }
    public function find(): ?array
    {
        if ($this->readFails) throw new \RuntimeException('simulated database failure');
        return $this->row;
    }
};
\think\Container::getInstance()->instance(\think\DbManager::class, $database);
$persistentStore = new \app\modules\Operations\Runtime\Infrastructure\ThinkOrmRuntimeConfigStore();
foreach (['{broken', 'null', '[]', '"polling"'] as $json) {
    $database->row = ['desired_mode' => 'realtime', 'settings_json' => $json, 'revision' => 1];
    assertTrue($persistentStore->current() === null, "invalid stored settings {$json} remain unavailable");
}
$database->row = ['settings_json' => '{}', 'revision' => 1];
assertTrue($persistentStore->current() === null, 'missing persisted selection is not defaulted to polling');
$database->row = ['desired_mode' => 'realtime', 'settings_json' => '{}', 'revision' => 1];
assertTrue($persistentStore->current()['desired_mode'] === 'realtime', 'valid persisted selection survives empty settings object');
$database->readFails = true;
assertTrue($persistentStore->current() === null, 'database read failure is represented as unavailable');

// 11. 未曾生效的参数不再出现在快照，旧输入明确拒绝。
foreach (['polling_backoff_factor', 'polling_max_interval_seconds', 'require_supervisor'] as $retiredKey) {
    assertTrue(!array_key_exists($retiredKey, CommunicationSettings::defaults()), "retired setting {$retiredKey} absent from defaults");
    assertTrue(!array_key_exists($retiredKey, CommunicationSettings::normalize([$retiredKey => 2])), "retired setting {$retiredKey} ignored in old storage");
    assertTrue(CommunicationSettings::validateInput([$retiredKey => 2]) !== [], "retired setting {$retiredKey} rejected from new writes");
}

echo "\n{$passCount} passed, {$failCount} failed\n";
exit($failCount === 0 ? 0 : 1);
