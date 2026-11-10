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
    $eventId = (int) Db::table('outbox_events')->insertGetId([
        'event_type' => 'signal.test', 'event_version' => 1,
        'aggregate_type' => 'test', 'aggregate_key' => '1',
        'payload' => '{}', 'occurred_at' => $stamp, 'created_at' => $stamp,
    ]);
    $repository = new ThinkDbDeliveryRepository();
    [$signalId, $businessId] = $repository->enqueue($eventId, [
        new DeliveryDefinition('test.signal', 'realtime', ExecutionMode::DAEMON_REQUIRED),
        new DeliveryDefinition('test.business', 'storage', ExecutionMode::DAEMON_REQUIRED),
    ], $now);
    $signal = Db::table('event_deliveries')->where('id', $signalId)->find();
    $business = Db::table('event_deliveries')->where('id', $businessId)->find();
    $assert($signal['status'] === 'completed' && $signal['policy_decision'] === 'skipped', 'polling must skip a new signal');
    $assert($business['status'] === 'pending', 'polling must retain independent business work');

    $claim = new DeliveryClaim('test-worker', ['realtime', 'storage'], [ExecutionMode::DAEMON_REQUIRED], 10,
        str_repeat('a', 32), $now->modify('+1 second'), $now->modify('+1 minute'));
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
        str_repeat('c', 32), $now->modify('+1 second'), $now->modify('+1 minute')));
    $assert($paused === [], 'unready realtime chain must not claim signal work');
    $assert(Db::table('event_deliveries')->where('id', $signalId)->value('status') === 'pending', 'paused signal must remain queued');

    $store = new ThinkDbSignalPolicyStore();
    $catalog = (new HandlerRegistry())->signalCatalog();
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
    Db::rollback();
    fwrite(STDOUT, "PASS isolated signal delivery and policy CAS E2E\n");
} catch (Throwable $exception) {
    Db::rollback();
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
