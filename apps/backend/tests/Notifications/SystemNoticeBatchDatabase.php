<?php
declare(strict_types=1);

use app\api\ApiException;
use app\modules\Platform\Eventing\Contract\OutboxEventRepository;
use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\HandlerRegistry;
use app\modules\Platform\Notifications\Contract\SystemNoticeBatchRepository;
use app\modules\Platform\Notifications\Domain\SystemNoticeBatch;
use app\modules\Platform\Notifications\Infrastructure\SystemNoticeBatchHandler;
use app\modules\Platform\Notifications\Infrastructure\SystemNotificationIssuedHandler;
use app\modules\Platform\Notifications\Public\SystemNoticeBatches;
use app\shared\Authorization\GrantSet;
use app\shared\Observability\AuditLogger;
use think\facade\Db;

$host = getenv('LC_TEST_DB_HOST');
if (!is_string($host) || $host === '') { echo "SKIP system notice batch database integration (LC_TEST_DB_HOST not configured)\n"; return; }
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) throw new RuntimeException('Publication tests require a local disposable database');
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$port = getenv('LC_TEST_DB_PORT') ?: '33309';
$username = getenv('LC_TEST_DB_USER') ?: 'root';
$password = getenv('LC_TEST_DB_PASSWORD') ?: '';
$database = 'lovecards_e2e_notice_batches_' . bin2hex(random_bytes(5));
$temporaryRoot = sys_get_temp_dir() . '/' . $database;
$pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$grants = GrantSet::fromRows([
    ['capability' => 'notifications.publish', 'scope' => 'global'], ['capability' => 'notifications.broadcast', 'scope' => 'global'],
]);
$expectStatus = static function (callable $action, int $status) use ($assert): void {
    try { $action(); } catch (ApiException $error) { $assert($error->getHttpStatus() === $status, 'wrong publication error status: ' . $error->getMessage()); return; }
    throw new RuntimeException('Expected publication error ' . $status);
};
try {
    $pdo->exec("USE `{$database}`");
    $pdo->exec((string) file_get_contents($root . '/database/schema.sql'));
    $pdo->exec((string) file_get_contents($root . '/database/seed.sql'));
    foreach (['HOSTNAME' => $host, 'HOSTPORT' => $port, 'DATABASE' => $database, 'USERNAME' => $username, 'PASSWORD' => $password, 'COLLATION' => 'utf8mb4_general_ci'] as $key => $value) putenv('PHP_DATABASE_' . $key . '=' . $value);
    putenv('LC_INSTANCE_ROOT=' . $temporaryRoot . '/instance'); putenv('LC_INSTANCE_ROOT_RESOLVED=' . $temporaryRoot . '/instance');
    $app = new \think\App($root); $app->setEnvName('system-notice-e2e'); $app->setRuntimePath($temporaryRoot . '/runtime/');
    (new ReflectionProperty($app, 'initializers'))->setValue($app, [\think\initializer\RegisterService::class, \think\initializer\BootService::class]);
    $app->initialize(); restore_exception_handler();
    $assert(Db::query('SELECT DATABASE() AS name')[0]['name'] === $database, 'ORM must remain inside the disposable database');
    $insertUser = static function (int $id, int $status = 0, int $roleId = 3, ?string $deleted = null): void {
        $now = date('Y-m-d H:i:s');
        Db::table('users')->insert(['id' => $id, 'uid' => 'T' . str_pad((string) $id, 11, '0', STR_PAD_LEFT), 'number' => (string) $id, 'email' => "notice-{$id}@example.test", 'phone' => '', 'username' => 'notice_user_' . $id, 'password' => 'fixture-unusable', 'status' => $status, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => $deleted]);
        Db::table('user_roles')->insert(['user_id' => $id, 'role_id' => $roleId, 'created_at' => $now]);
    };
    foreach (range(100, 302) as $id) $insertUser($id);
    $insertUser(9000, 0, 4); $insertUser(9001, 0, 3, date('Y-m-d H:i:s')); $insertUser(9002, 1); $insertUser(9003, 3);
    $candidate = SystemNoticeBatches::candidates('#100');
    $assert($candidate === [['id' => 100, 'username' => 'notice_user_100']], 'candidate must resolve numeric ID and expose only identity fields');
    $assert(SystemNoticeBatches::candidates('9000') === [], 'guest users must not be candidates');
    $assert(count(SystemNoticeBatches::candidates('notice_user_', 5)) === 5, 'candidate results must be bounded');
    $input = ['request_key' => 'db:selected:1', 'audience' => 'selected', 'recipient_ids' => [101, 100, 101], 'message' => '测试通告', 'action_url' => '/settings', 'severity' => 'info'];
    $preview = SystemNoticeBatches::prepare($input, 100, $grants);
    $assert($preview['status'] === 'prepared' && $preview['recipient_count'] === 2 && $preview['recipient_ids'] === [100, 101], 'prepare must freeze a canonical selected audience before sending');
    $assert(Db::table('notifications')->count() === 0 && Db::table('outbox_events')->count() === 0, 'preparing the preview must not publish any notification');
    $same = SystemNoticeBatches::prepare(array_replace($input, ['recipient_ids' => [100, 101]]), 100, $grants);
    $assert($same['id'] === $preview['id'], 'same content must reuse the existing durable request');
    $expectStatus(static fn() => SystemNoticeBatches::prepare(array_replace($input, ['message' => 'changed']), 100, $grants), 409);
    $expectStatus(static fn() => SystemNoticeBatches::prepare(array_replace($input, ['request_key' => 'db:guest', 'recipient_ids' => [9000]]), 100, $grants), 422);
    $expectStatus(static fn() => SystemNoticeBatches::prepare(array_replace($input, ['request_key' => 'db:missing', 'recipient_ids' => [999999]]), 100, $grants), 422);
    $expectStatus(static fn() => SystemNoticeBatches::get($preview['id'], 101), 404);
    $expectStatus(static fn() => SystemNoticeBatches::confirm($preview['id'], 100, new GrantSet()), 403);
    $childFile = $temporaryRoot . '/parallel-publication.php';
    file_put_contents($childFile, '<?php declare(strict_types=1); $root=' . var_export($root, true) . ';' . <<<'PHP'
require $root . '/vendor/autoload.php';
$database = getenv('PHP_DATABASE_DATABASE');
if (!is_string($database) || preg_match('/^lovecards_e2e_notice_batches_[a-f0-9]{10}$/', $database) !== 1) throw new RuntimeException('Unsafe concurrent fixture database');
$app = new \think\App($root); $app->setEnvName('system-notice-e2e'); $app->setRuntimePath(sys_get_temp_dir() . '/' . $database . '/child-' . getmypid() . '/');
(new ReflectionProperty($app, 'initializers'))->setValue($app, [\think\initializer\RegisterService::class, \think\initializer\BootService::class]);
$app->initialize(); restore_exception_handler();
if (\think\facade\Db::query('SELECT DATABASE() AS name')[0]['name'] !== $database) throw new RuntimeException('Concurrent fixture escaped its database');
$grants = \app\shared\Authorization\GrantSet::fromRows([['capability'=>'notifications.publish','scope'=>'global'],['capability'=>'notifications.broadcast','scope'=>'global']]);
$input=json_decode(base64_decode($argv[2]),true,512,JSON_THROW_ON_ERROR);
$result=$argv[1]==='prepare' ? \app\modules\Platform\Notifications\Public\SystemNoticeBatches::prepare($input,100,$grants) : \app\modules\Platform\Notifications\Public\SystemNoticeBatches::confirm((int)$input['id'],100,$grants);
echo json_encode(['id'=>$result['id'],'status'=>$result['status']]),PHP_EOL;
PHP
    );
    $parallel = static function (string $operation, array $first, array $second) use ($childFile, $assert): array {
        $workers = [];
        foreach ([$first, $second] as $data) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, $childFile, $operation, base64_encode(json_encode($data, JSON_THROW_ON_ERROR))], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            $assert(is_resource($process), 'concurrent publication worker must start');
            fclose($pipes[0]); $workers[] = [$process, $pipes];
        }
        $results = [];
        foreach ($workers as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            $assert(proc_close($process) === 0, 'concurrent publication worker failed: ' . $errors . $output);
            $results[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        }
        return $results;
    };
    $parallelInput = array_replace($input, ['request_key' => 'db:parallel']);
    $preparedTogether = $parallel('prepare', $parallelInput, array_replace($parallelInput, ['recipient_ids' => [100, 101]]));
    $assert($preparedTogether[0]['id'] === $preparedTogether[1]['id'], 'concurrent duplicate prepare must return the same batch');
    $beforeConfirm = Db::table('outbox_events')->count(); $beforeConfirmAudit = Db::table('audit_logs')->count();
    $confirmedTogether = $parallel('confirm', ['id' => $preparedTogether[0]['id']], ['id' => $preparedTogether[0]['id']]);
    $assert($confirmedTogether[0]['id'] === $confirmedTogether[1]['id'] && Db::table('outbox_events')->count() === $beforeConfirm + 1 && Db::table('audit_logs')->count() === $beforeConfirmAudit + 1, 'concurrent confirm must publish exactly one chunk and one audit');
    $queued = SystemNoticeBatches::confirm($preview['id'], 100, $grants);
    $eventCount = Db::table('outbox_events')->count(); $auditCount = Db::table('audit_logs')->count();
    SystemNoticeBatches::confirm($preview['id'], 100, $grants);
    $assert(Db::table('outbox_events')->count() === $eventCount && Db::table('audit_logs')->count() === $auditCount, 'duplicate confirmation must neither republish nor create pseudo-audit');
    $drain = static function () use ($assert): void {
        $registry = app(HandlerRegistry::class);
        for ($guard = 0; $guard < 2000; $guard++) {
            $delivery = Db::table('event_deliveries')->where('queue_name', 'notifications')->where('status', 'pending')->order('id')->find();
            if (!$delivery) return;
            $event = app(OutboxEventRepository::class)->findById((int) $delivery['event_id']);
            $registration = $registry->find($delivery['handler_key']);
            $assert($event !== null && $registration !== null, 'registered notification task must have its event and handler');
            $attempt = (int) $delivery['attempt_count'] + 1;
            Db::table('event_deliveries')->where('id', $delivery['id'])->update(['status' => 'processing', 'attempt_count' => $attempt]);
            $registration->handler()->handle($event, new DeliveryContext((int) $delivery['id'], (int) $delivery['event_id'], $delivery['handler_key'], $attempt));
            Db::table('event_deliveries')->where('id', $delivery['id'])->update(['status' => 'completed', 'completed_at' => gmdate('Y-m-d H:i:s')]);
        }
        throw new RuntimeException('Batch drain exceeded the bounded fixture budget');
    };
    $drain();
    $done = SystemNoticeBatches::get($preview['id'], 100);
    $assert($done['status'] === 'completed' && $done['generated_count'] === 2 && $done['pending_count'] === 0 && $done['completed_at'] !== null, 'projection completion must produce a durable truthful receipt');
    $self = Db::table('notifications')->where('recipient_id', 100)->find();
    $assert($self !== null && $self['actor_id'] === null && $self['read_at'] === null, 'publisher must receive its own selected system notice without silently marking it read');
    $projected = Db::table('system_notice_recipients')->where('batch_id', $preview['id'])->where('user_id', 100)->find();
    $event = app(OutboxEventRepository::class)->findById((int) $projected['issued_event_id']);
    $notificationsBeforeReplay = Db::table('notifications')->count();
    (new SystemNotificationIssuedHandler())->handle($event, new DeliveryContext((int) $projected['delivery_id'], (int) $projected['issued_event_id'], 'notifications.system-notification-issued', 2));
    $assert(Db::table('notifications')->count() === $notificationsBeforeReplay && SystemNoticeBatches::get($preview['id'], 100)['generated_count'] === 2, 'projection replay must not duplicate notification rows or counters');
    $allInput = ['request_key' => 'db:all:1', 'audience' => 'all', 'recipient_ids' => [], 'message' => '全站测试通告'];
    $all = SystemNoticeBatches::prepare($allInput, 100, $grants);
    $publishOnly = GrantSet::fromRows([['capability' => 'notifications.publish', 'scope' => 'global']]);
    $expectStatus(static fn() => SystemNoticeBatches::confirm($all['id'], 100, $publishOnly), 403);
    $assert($all['recipient_ids'] === [] && $all['recipients'] === [], 'global preview must not enumerate the whole site through the API');
    $assert($all['recipient_count'] >= 205, 'banned and pending formal accounts remain eligible for system notices');
    $insertUser(9100);
    $allQueued = SystemNoticeBatches::confirm($all['id'], 100, $grants);
    $batchRow = Db::table('system_notice_batches')->where('id', $all['id'])->find();
    $firstEvent = app(OutboxEventRepository::class)->findById((int) $batchRow['dispatch_event_id']);
    $firstContext = new DeliveryContext((int) $batchRow['dispatch_delivery_id'], (int) $batchRow['dispatch_event_id'], SystemNoticeBatch::CHUNK_HANDLER, 1);
    (new SystemNoticeBatchHandler())->handle($firstEvent, $firstContext);
    $assert(Db::table('system_notice_recipients')->where('batch_id', $all['id'])->whereNotNull('issued_event_id')->count() === 100, 'one batch handler must issue at most one 100-recipient chunk');
    (new SystemNoticeBatchHandler())->handle($firstEvent, $firstContext);
    $assert(Db::table('system_notice_recipients')->where('batch_id', $all['id'])->whereNotNull('issued_event_id')->count() === 100, 'replaying an old chunk must not issue the next chunk or duplicate children');
    Db::table('users')->where('id', 101)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    $drain();
    $allDone = SystemNoticeBatches::get($all['id'], 100);
    $assert($allDone['status'] === 'completed' && $allDone['generated_count'] + $allDone['skipped_count'] === $all['recipient_count'] && $allDone['skipped_count'] === 1, 'frozen audience must settle deleted recipients as skipped');
    $assert(Db::table('system_notice_recipients')->where('batch_id', $all['id'])->where('user_id', 9100)->count() === 0, 'later registrations must not enter a frozen all-user publication');
    $retryPreview = SystemNoticeBatches::prepare(['request_key' => 'db:retry:1', 'audience' => 'selected', 'recipient_ids' => [102], 'message' => '重试测试'], 100, $grants);
    SystemNoticeBatches::confirm($retryPreview['id'], 100, $grants);
    $retryRow = Db::table('system_notice_batches')->where('id', $retryPreview['id'])->find();
    Db::table('event_deliveries')->where('id', $retryRow['dispatch_delivery_id'])->update(['status' => 'dead', 'attempt_count' => 5]);
    $failed = SystemNoticeBatches::get($retryPreview['id'], 100);
    $assert($failed['status'] === 'failed' && $failed['can_retry'], 'fanout dead letter must be recoverable without false recipient failures');
    $restarted = SystemNoticeBatches::retry($retryPreview['id'], 100, $grants, ['expected_generation' => 1, 'reason' => 'fixture recovery']);
    $assert($restarted['generation'] === 2, 'manual recovery must advance the durable command generation once');
    SystemNoticeBatches::retry($retryPreview['id'], 100, $grants, ['expected_generation' => 1, 'reason' => 'fixture recovery']);
    $assert(SystemNoticeBatches::get($retryPreview['id'], 100)['generation'] === 2, 'replayed recovery command must not grant another attempt');
    $drain();
    $assert(SystemNoticeBatches::get($retryPreview['id'], 100)['generated_count'] === 1, 'resuming a failed chunk must use its original task and produce one notification');
    $partial = SystemNoticeBatches::prepare(['request_key' => 'db:partial:1', 'audience' => 'selected', 'recipient_ids' => [102, 103], 'message' => '部分失败测试'], 100, $grants);
    SystemNoticeBatches::confirm($partial['id'], 100, $grants);
    $partialRow = Db::table('system_notice_batches')->where('id', $partial['id'])->find();
    $partialEvent = app(OutboxEventRepository::class)->findById((int) $partialRow['dispatch_event_id']);
    (new SystemNoticeBatchHandler())->handle($partialEvent, new DeliveryContext((int) $partialRow['dispatch_delivery_id'], (int) $partialRow['dispatch_event_id'], SystemNoticeBatch::CHUNK_HANDLER, 1));
    $failedRecipient = Db::table('system_notice_recipients')->where('batch_id', $partial['id'])->where('user_id', 102)->find();
    Db::table('event_deliveries')->where('id', $failedRecipient['delivery_id'])->update(['status' => 'dead', 'attempt_count' => 5]);
    $drain();
    $partialReceipt = SystemNoticeBatches::get($partial['id'], 100);
    $assert($partialReceipt['status'] === 'partial_failure' && $partialReceipt['generated_count'] === 1 && $partialReceipt['failed_count'] === 1 && $partialReceipt['pending_count'] === 0, 'failed recipient projection must remain distinct from a generated notification');
    SystemNoticeBatches::retry($partial['id'], 100, $grants, ['expected_generation' => 1, 'reason' => 'retry fixture recipient']);
    $drain();
    $partialDone = SystemNoticeBatches::get($partial['id'], 100);
    $assert($partialDone['status'] === 'completed' && $partialDone['generated_count'] === 2 && $partialDone['failed_count'] === 0, 'bounded retry must recover only failed original recipient deliveries');
    $assert((int) Db::table('event_deliveries')->where('id', $failedRecipient['delivery_id'])->value('attempt_count') === 6, 'manual retry must authorize one extra cumulative attempt');
    $rollback = SystemNoticeBatches::prepare(['request_key' => 'db:rollback:1', 'audience' => 'selected', 'recipient_ids' => [103], 'message' => '审计失败测试'], 100, $grants);
    $eventBefore = Db::table('outbox_events')->count();
    $originalAudit = app(AuditLogger::class);
    $app->instance(AuditLogger::class, new class implements AuditLogger { public function record(?int $actorId, string $action, string $resourceType, ?string $resourceId, array $metadata = []): void { throw new RuntimeException('fixture audit unavailable'); } });
    $app->delete(\app\modules\Platform\Notifications\Application\ManageSystemNoticeBatches::class);
    $auditFailed = false;
    try { SystemNoticeBatches::confirm($rollback['id'], 100, $grants); } catch (RuntimeException $error) { $auditFailed = $error->getMessage() === 'fixture audit unavailable'; }
    $assert($auditFailed && Db::table('outbox_events')->count() === $eventBefore && Db::table('system_notice_batches')->where('id', $rollback['id'])->value('confirmed_at') === null, 'audit failure must roll back confirmation and all outbox records');
    $app->instance(AuditLogger::class, $originalAudit); $app->delete(\app\modules\Platform\Notifications\Application\ManageSystemNoticeBatches::class);
    $broken = SystemNoticeBatches::prepare(['request_key' => 'db:broken:1', 'audience' => 'selected', 'recipient_ids' => [104], 'message' => '回执核对测试'], 100, $grants);
    SystemNoticeBatches::confirm($broken['id'], 100, $grants);
    $brokenRow = Db::table('system_notice_batches')->where('id', $broken['id'])->find();
    Db::table('event_deliveries')->where('id', $brokenRow['dispatch_delivery_id'])->update(['status' => 'completed']);
    $expectStatus(static fn() => SystemNoticeBatches::get($broken['id'], 100), 503);
    Db::table('event_deliveries')->where('id', $brokenRow['dispatch_delivery_id'])->update(['status' => 'pending']);
    $drain();
    $beforeGrants = Db::table('role_grants')->order('role_id,capability,scope')->select()->toArray();
    $migration = (string) file_get_contents($root . '/database/migrations/20261127000002.sql');
    Db::table('capabilities')->where('capability', 'notifications.read')->delete();
    Db::table('capabilities')->insert(['capability' => 'notifications.read', 'description' => '查看站内通知记录元数据', 'risk_level' => 2]);
    $pdo->exec($migration); $pdo->exec($migration);
    $assert(Db::table('capabilities')->where('capability', 'notifications.read')->value('description') === '查看站内通知聚合统计', 'migration replay must update the narrowed statistics capability label');
    $assert($beforeGrants === Db::table('role_grants')->order('role_id,capability,scope')->select()->toArray(), 'migration replay must not grant any sending capability to existing roles');
    echo "PASS system notice batch database integration\n";
} finally {
    if (preg_match('/^lovecards_e2e_notice_batches_[a-f0-9]{10}$/', $database) !== 1) throw new RuntimeException('Unsafe cleanup database name');
    $pdo->exec("DROP DATABASE `{$database}`");
    if (is_dir($temporaryRoot)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporaryRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
        rmdir($temporaryRoot);
    }
}
