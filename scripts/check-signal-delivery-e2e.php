<?php
declare(strict_types=1);

// 仅在一次性本地数据库中验证生产投递仓储和策略 CAS。
if (getenv('LOVECARDS_E2E_ISOLATED') !== '1'
    || !preg_match('/^lovecards_e2e_[a-z0-9_]+$/', (string) getenv('DATABASE_DATABASE'))
    || !in_array(getenv('DATABASE_HOSTNAME'), ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "Refusing non-isolated signal delivery test\n");
    exit(1);
}

$backend = __DIR__ . '/../apps/backend';
require $backend . '/vendor/autoload.php';
$app = new \think\App($backend . DIRECTORY_SEPARATOR);
$app->initialize();

use app\modules\Platform\Eventing\Domain\DeliveryClaim;
use app\modules\Platform\Eventing\Domain\DeliveryDefinition;
use app\modules\Platform\Eventing\Infrastructure\ThinkDbDeliveryRepository;
use app\modules\Platform\Eventing\Infrastructure\ThinkDbSignalPolicyStore;
use app\modules\Platform\Eventing\Public\ExecutionMode;
use app\modules\Platform\Eventing\Public\HandlerRegistry;
use app\modules\Platform\Eventing\Public\DeliveryContext;
use think\facade\Db;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$stamp = $now->format('Y-m-d H:i:s.u');

// 第二连接必须等待入队读取持有的模式行锁；证明切换与入队可串行。
Db::startTrans();
try {
    $selected = $app->make(\app\modules\Operations\Runtime\Public\RuntimeCommunication::class)->selectedModeForEnqueue();
    $assert($selected === 'polling', 'new install must start in polling mode');
    $other = new PDO('mysql:host=' . getenv('DATABASE_HOSTNAME') . ';port=' . getenv('DATABASE_HOSTPORT')
        . ';dbname=' . getenv('DATABASE_DATABASE') . ';charset=utf8mb4',
        (string) getenv('DATABASE_USERNAME'), (string) getenv('DATABASE_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $other->exec('SET innodb_lock_wait_timeout = 1');
    $locked = false;
    try {
        $other->exec("UPDATE runtime_config_state SET desired_mode = 'realtime' WHERE id = 1");
    } catch (PDOException $exception) {
        $locked = (int) ($exception->errorInfo[1] ?? 0) === 1205;
    }
    $assert($locked, 'mode switch must wait for the enqueue mode lock');
} finally {
    Db::rollback();
}

Db::startTrans();
try {
    Db::table('runtime_config_state')->where('id', 1)->update(['desired_mode' => 'polling']);
    Db::table('eventing_consumer_state')->where('id', 1)->update(['mode' => 'resident', 'state' => 'active']);
    $eventId = (int) Db::table('outbox_events')->insertGetId([
        'event_type' => 'signal.test', 'event_version' => 1,
        'aggregate_type' => 'test', 'aggregate_key' => '1',
        'payload' => '{}', 'occurred_at' => $stamp, 'created_at' => $stamp,
    ]);
    $repository = new ThinkDbDeliveryRepository();
    [$signalId, $businessId] = $repository->enqueue($eventId, [
        DeliveryDefinition::fromRegistration(\app\EventHandlers::create()->find('realtime.message-sent')),
        new DeliveryDefinition('test.business', 'storage', ExecutionMode::DAEMON_REQUIRED),
    ], $now);
    $signal = Db::table('event_deliveries')->where('id', $signalId)->find();
    $business = Db::table('event_deliveries')->where('id', $businessId)->find();
    $assert($signal['status'] === 'completed' && $signal['policy_decision'] === 'skipped', 'polling must skip a new signal');
    $assert($business['status'] === 'pending', 'polling must retain independent business work');

    $claim = new DeliveryClaim('test-worker', ['realtime', 'storage'], [ExecutionMode::DAEMON_REQUIRED], 10,
        str_repeat('a', 32), $now->modify('+1 second'), $now->modify('+1 minute'), 'resident');
    $claimed = $repository->claim($claim);
    $assert(count($claimed) === 1 && $claimed[0]->id() === $businessId, 'polling claim must exclude realtime without blocking business work');

    Db::table('event_deliveries')->where('id', $signalId)->update([
        'status' => 'processing', 'policy_decision' => null, 'claim_token' => str_repeat('b', 32),
        'claimed_by' => 'old-worker', 'lease_expires_at' => $now->modify('+1 minute')->format('Y-m-d H:i:s.u'),
    ]);
    $assert($repository->settleRealtimeForPolling() === 1, 'switch to polling must settle a processing signal');
    $settled = Db::table('event_deliveries')->where('id', $signalId)->find();
    $assert($settled['status'] === 'completed' && $settled['policy_decision'] === 'skipped' && $settled['claim_token'] === null,
        'settlement must clear old worker claim');
    $assert(!$repository->complete($signalId, str_repeat('b', 32), $now), 'old worker completion must be fenced');

    Db::table('event_deliveries')->where('id', $signalId)->update([
        'status' => 'processing', 'policy_decision' => null, 'claim_token' => str_repeat('e', 32),
        'completed_at' => null,
    ]);
    $assert($repository->complete($signalId, str_repeat('e', 32), $now, 'applied'), 'current worker completion must succeed');
    $applied = Db::table('event_deliveries')->where('id', $signalId)->find();
    $assert($applied['status'] === 'completed' && $applied['policy_decision'] === 'applied',
        'published decision must persist atomically with completion');

    Db::table('runtime_config_state')->where('id', 1)->update(['desired_mode' => 'realtime']);
    Db::table('event_deliveries')->where('id', $signalId)->update(['status' => 'pending', 'policy_decision' => null, 'completed_at' => null]);
    $paused = $repository->claim(new DeliveryClaim('test-worker', ['realtime'], [ExecutionMode::DAEMON_REQUIRED], 10,
        str_repeat('c', 32), $now->modify('+1 second'), $now->modify('+1 minute'), 'resident'));
    $assert($paused === [], 'unready realtime chain must not claim signal work');
    $assert(Db::table('event_deliveries')->where('id', $signalId)->value('status') === 'pending', 'paused signal must remain queued');

    $store = new ThinkDbSignalPolicyStore();
    $catalog = \app\EventHandlers::create()->signalCatalog();
    $revision = $store->catalog($catalog)['revision'];
    $audits = 0;
    $updated = $store->applyChanges($catalog, [['key' => 'messages.new', 'enabled' => false]], $revision, 7,
        static function (array $before, array $after, int $nextRevision) use (&$audits, $revision): void {
            $audits++;
            if (($after['messages.new'] ?? null) !== false || $nextRevision !== $revision + 1) throw new RuntimeException('audit state mismatch');
        });
    $assert($updated['revision'] === $revision + 1 && $audits === 1, 'policy CAS must return its own revision and audit once');
    $assert($store->enabled('messages.new', $catalog) === false, 'policy override must take effect');
    $stale = false;
    try {
        $store->applyChanges($catalog, [['key' => 'messages.new', 'enabled' => true]], $revision, 7,
            static function (): void { throw new RuntimeException('stale CAS reached audit'); });
    } catch (RuntimeException $exception) {
        $stale = $exception->getMessage() === 'stale-revision';
    }
    $assert($stale, 'stale policy CAS must reject without audit');
    $auditFailure = false;
    try {
        $store->applyChanges($catalog, [['key' => 'messages.new', 'enabled' => true]], $revision + 1, 7,
            static function (): void { throw new RuntimeException('audit-write-failed'); });
    } catch (RuntimeException $exception) {
        $auditFailure = $exception->getMessage() === 'audit-write-failed';
    }
    $assert($auditFailure, 'audit failure must reject policy update');
    $assert($store->catalog($catalog)['revision'] === $revision + 1 && $store->enabled('messages.new', $catalog) === false,
        'audit failure must roll back the policy row');

    $registry = \app\EventHandlers::create();
    $signals = array_column($catalog, null, 'key');
    $enqueue = static function (string $handler, ?array $origin = null) use ($registry, $repository, $stamp, $now): int {
        $id = (int) Db::table('outbox_events')->insertGetId([
            'event_type' => 'signal.test', 'event_version' => 1, 'aggregate_type' => 'test',
            'aggregate_key' => bin2hex(random_bytes(8)), 'payload' => '{}', 'occurred_at' => $stamp, 'created_at' => $stamp,
        ]);
        return $repository->enqueue($id, [DeliveryDefinition::fromRegistration($registry->find($handler), $origin)], $now)[0];
    };
    $claimContext = static function (int $id): DeliveryContext {
        $row = Db::table('event_deliveries')->where('id', $id)->find();
        Db::table('event_deliveries')->where('id', $id)->update(['status' => 'processing', 'claim_token' => str_repeat('f', 32),
            'lease_expires_at' => gmdate('Y-m-d H:i:s', time() + 60)]);
        return new DeliveryContext($id, (int) $row['event_id'], $row['handler_key'], 1, null, 'signal.test', str_repeat('f', 32));
    };
    $change = static function (string $key, bool $enabled) use ($store, $catalog): void {
        $store->applyChanges($catalog, [['key' => $key, 'enabled' => $enabled]], $store->catalog($catalog)['revision'], 1, static function (): void {});
    };
    $change('messages.new', true);
    $root = $enqueue('realtime.card-published');
    $unrelated = $enqueue('realtime.message-sent');
    $context = $claimContext($root);
    $assert($store->authorize($signals['feed.published'], $context), 'root obtains original generation eligibility');
    $origin = $store->continueSignal($signals['feed.published'], $context, static fn(array $origin): array => $origin);
    $child = $enqueue('realtime.card-feed-batch', $origin);
    $change('feed.published', false);
    $disabledRoot = $enqueue('realtime.card-published');
    $context->assertSignalWindow();
    $assert($store->continueSignal($signals['feed.published'], $context, static fn(): bool => true) === null,
        'disabled key cannot create continuation even from previously authorized root');
    $change('feed.published', true);
    $assert(!$store->authorize($signals['feed.published'], $claimContext($root)), 'reopen cannot revive old root');
    $assert(!$store->authorize($signals['feed.published'], $claimContext($child)), 'reopen cannot revive old continuation');
    $assert(!$store->authorize($signals['feed.published'], $claimContext($disabledRoot)), 'disabled registration remains skipped');
    $assert($store->authorize($signals['messages.new'], $claimContext($unrelated)), 'unrelated key retains its generation');
    $fresh = $enqueue('realtime.card-published');
    $assert($store->authorize($signals['feed.published'], $claimContext($fresh)), 'newly registered generation may send');
    $other->exec('SET innodb_lock_wait_timeout = 1');
    $locked = false;
    try { $other->exec("UPDATE signal_policy_state SET revision = revision + 1 WHERE id = 1"); }
    catch (PDOException $exception) { $locked = (int) ($exception->errorInfo[1] ?? 0) === 1205; }
    $assert($locked, 'signal switch must wait for transaction holding original eligibility lock');
    Db::rollback();

    // 仅隔离库：模拟多步 DDL 在中间失败，复用生产解析器重放受审迁移。
    $legacyIds = [];
    foreach (['pending', 'processing', 'dead'] as $status) {
        Db::startTrans();
        $legacyId = $enqueue('realtime.card-published');
        Db::table('event_deliveries')->where('id', $legacyId)->update(['status' => $status, 'policy_decision' => null]);
        Db::commit();
        $legacyIds[] = $legacyId;
    }
    Db::execute('ALTER TABLE event_deliveries DROP COLUMN signal_generation');
    Db::execute('ALTER TABLE signal_policy_state MODIFY COLUMN generations_json LONGTEXT NULL');
    Db::table('signal_policy_state')->where('id', 1)->update(['generations_json' => null]);
    $migration = file_get_contents($backend . '/database/migrations/20261111000005.sql');
    \app\shared\Infrastructure\Persistence\SqlStatementExecutor::execute($migration);
    \app\shared\Infrastructure\Persistence\SqlStatementExecutor::execute($migration);
    $assert(Db::table('signal_policy_state')->where('id', 1)->value('generations_json') === '{}', 'partial DDL replay initializes generation metadata');
    $column = Db::query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'signal_policy_state' AND COLUMN_NAME = 'generations_json'");
    $assert(($column[0]['IS_NULLABLE'] ?? null) === 'NO', 'partial DDL replay completes not-null constraint');
    foreach ($legacyIds as $legacyId) {
        $legacy = Db::table('event_deliveries')->where('id', $legacyId)->find();
        $assert($legacy['status'] === 'completed' && $legacy['policy_decision'] === 'skipped' && $legacy['claim_token'] === null,
            'unknown historical eligibility must be permanently skipped');
    }
    fwrite(STDOUT, "PASS isolated signal delivery and policy CAS E2E\n");
} catch (Throwable $exception) {
    Db::rollback();
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
