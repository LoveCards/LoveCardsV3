<?php
declare(strict_types=1);

use app\modules\Operations\Administration\Service\AdminReadModel;
use app\modules\Operations\SystemLifecycle\Infrastructure\MigrationCatalog;
use app\modules\Platform\Notifications\Public\AdminNotifications;
use app\modules\Platform\Notifications\Transport\Api\AdminNotificationResource;
use think\facade\Db;

$host = getenv('LC_TEST_DB_HOST');
if (!is_string($host) || $host === '') {
    echo "SKIP admin notification database integration (LC_TEST_DB_HOST not configured)\n";
    return;
}
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Notification integration requires a local disposable database');
}
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$database = 'lovecards_e2e_notifications_' . bin2hex(random_bytes(5));
$port = getenv('LC_TEST_DB_PORT') ?: '3306';
$user = getenv('LC_TEST_DB_USER') ?: 'root';
$password = getenv('LC_TEST_DB_PASSWORD') ?: '';
$pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$temporaryRoot = sys_get_temp_dir() . '/' . $database;
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$ids = static fn(array $page): array => array_map('intval', array_column($page['data'], 'id'));

try {
    $pdo->exec("USE `{$database}`");
    // 只建立本切片涉及的真实生产表结构，避免依赖其他模块的测试数据。
    $schema = (string) file_get_contents($root . '/database/schema.sql');
    foreach (['notifications', 'capabilities', 'role_grants'] as $table) {
        if (preg_match('/CREATE TABLE `' . $table . '` \([\s\S]*?\) ENGINE=[^;]+;/u', $schema, $match) !== 1) {
            throw new RuntimeException('Missing fixture table ' . $table);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec($match[0]);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    foreach (['HOSTNAME' => $host, 'HOSTPORT' => $port, 'DATABASE' => $database, 'USERNAME' => $user, 'PASSWORD' => $password, 'COLLATION' => 'utf8mb4_general_ci'] as $key => $value) {
        putenv('PHP_DATABASE_' . $key . '=' . $value);
    }
    putenv('LC_INSTANCE_ROOT=' . $temporaryRoot . '/instance');
    putenv('LC_INSTANCE_ROOT_RESOLVED=' . $temporaryRoot . '/instance');
    $app = new \think\App($root);
    $app->setEnvName('notification-e2e');
    $app->setRuntimePath($temporaryRoot . '/runtime/');
    $initializers = new ReflectionProperty($app, 'initializers');
    $initializers->setValue($app, [\think\initializer\RegisterService::class, \think\initializer\BootService::class]);
    $app->initialize();
    $assert(Db::query('SELECT DATABASE() AS name')[0]['name'] === $database, 'ORM must target the isolated fixture database');

    $empty = AdminNotifications::paginate();
    $assert($empty === ['data' => [], 'total' => 0, 'per_page' => 20, 'current_page' => 1, 'last_page' => 1], 'empty pagination must preserve the standard envelope and defaults');
    $assert(AdminNotifications::stats() === ['total' => 0, 'unread' => 0, 'recipients' => 0, 'types' => []], 'empty statistics must preserve the old count shape');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $insert = $pdo->prepare('INSERT INTO notifications (id,recipient_id,type,actor_id,resource_type,resource_id,payload,dedupe_key,read_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ([
        [1, 48, 'content.replied', 9, 'comment', '17', '{"private":"text"}', 'dedupe-1', null, '2026-11-26 23:59:59'],
        [2, 48, 'content.card_liked', null, 'card', '38472', '{"private":"text"}', 'dedupe-2', '2026-11-27 09:00:00', '2026-11-27 00:00:00'],
        [3, 7, 'legacy.unknown', null, null, null, '{"private":"text"}', 'dedupe-3', null, '2026-11-27 00:00:00'],
        [4, 7, 'content.replied', 48, 'comment', '18', '{"private":"text"}', 'dedupe-4', null, '2026-11-28 00:00:00'],
    ] as $row) $insert->execute($row);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $snapshot = $pdo->query('SELECT * FROM notifications ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $default = AdminNotifications::paginate();
    $assert($ids($default) === [4, 3, 2, 1] && $default['total'] === 4, 'all stored types must participate in deterministic created_at/id order');
    $first = AdminNotifications::paginate(['list_rows' => 2, 'page' => 1]);
    $second = AdminNotifications::paginate(['list_rows' => 2, 'page' => 2]);
    $assert(array_merge($ids($first), $ids($second)) === [4, 3, 2, 1] && $first['total'] === 4 && $second['last_page'] === 2, 'pagination count, tie-break and page boundaries must agree');
    $window = AdminNotifications::paginate(['from' => '2026-11-27 00:00:00', 'to' => '2026-11-28 00:00:00']);
    $assert($ids($window) === [3, 2] && $window['total'] === 2, 'date filters must include from and exclude to without timezone conversion');
    $assert($ids(AdminNotifications::paginate(['recipient_id' => 48])) === [2, 1], 'recipient filter must use database identity');
    $assert($ids(AdminNotifications::paginate(['read_state' => 'read'])) === [2], 'read filter must use persisted read_at');
    $assert($ids(AdminNotifications::paginate(['read_state' => 'unread'])) === [4, 3, 1], 'unread filter must include all types');
    $assert($ids(AdminNotifications::paginate(['type' => 'legacy.unknown'])) === [3], 'unknown stored type must be queryable by its original value');
    $assert(AdminNotifications::paginate(['type' => "unknown' OR 1=1 --"])['total'] === 0, 'type filter must remain a bound parameter');
    $assert(AdminNotifications::paginate(['page' => 100])['data'] === [], 'out-of-range pages must return empty rows with the correct total');
    $assert(AdminNotifications::get(99999) === null, 'missing metadata must remain distinguishable from an empty item');
    $detail = AdminNotifications::get(3);
    $projected = AdminNotificationResource::make($detail);
    $assert($projected['type'] === 'legacy.unknown' && $projected['type_label'] === '未知类型', 'detail must preserve unknown types');
    $assert(!array_key_exists('payload', $detail) && !array_key_exists('dedupe_key', $detail), 'query projection must not fetch body or dedupe even internally');
    $assert(AdminNotificationResource::make($default['data'][1]) === $projected, 'list and detail facts must match');
    $stats = AdminNotifications::stats();
    $assert($stats['total'] === 4 && $stats['unread'] === 3 && $stats['recipients'] === 2 && array_sum(array_column($stats['types'], 'count')) === 4, 'statistics must count every stored notification type: ' . json_encode($stats));
    $assert((new AdminReadModel())->notificationsStats() === $stats, 'legacy operations statistics must delegate without response drift');
    $assert($pdo->query('SELECT * FROM notifications ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $snapshot, 'all admin reads must preserve read_at, payload, dedupe and row count');

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec("INSERT INTO role_grants (role_id,capability,scope,created_at) VALUES (2,'admin.access','global','2026-11-27 00:00:00'),(6,'communications.read','global','2026-11-27 00:00:00')");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $grantsBefore = $pdo->query('SELECT * FROM role_grants ORDER BY role_id,capability')->fetchAll(PDO::FETCH_ASSOC);
    $cleanIndex = $pdo->query("SELECT column_name FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='notifications' AND index_name='idx_notifications_created' ORDER BY seq_in_index")->fetchAll(PDO::FETCH_COLUMN);
    $assert($cleanIndex === ['created_at', 'id'], 'new installations must include the default notification ordering index');
    $pdo->exec('ALTER TABLE notifications DROP INDEX idx_notifications_created');
    $migration = null;
    foreach ((new MigrationCatalog())->discover($root . '/database/migrations') as $candidate) {
        if ($candidate->descriptor()->id() === '20261127000001') $migration = $candidate;
    }
    $assert($migration !== null && !$migration->descriptor()->hasContract(), 'capability registration must be a standard non-contract migration');
    $migration->preflight();
    $migration->expand();
    $migration->expand();
    $migration->verify();
    $assert((int) $pdo->query("SELECT COUNT(*) FROM capabilities WHERE capability='notifications.read'")->fetchColumn() === 1, 'migration replay must register one capability');
    $assert($pdo->query('SELECT * FROM role_grants ORDER BY role_id,capability')->fetchAll(PDO::FETCH_ASSOC) === $grantsBefore, 'migration replay must not grant new metadata access to existing roles');
    $index = $pdo->query("SELECT column_name FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='notifications' AND index_name='idx_notifications_created' ORDER BY seq_in_index")->fetchAll(PDO::FETCH_COLUMN);
    $assert($index === ['created_at', 'id'], 'clean install and forward migration must preserve the one default ordering index');
    // 代表性数据量下验证优化器实际使用默认索引，不使用 FORCE INDEX 掩盖查询计划。
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->beginTransaction();
    $benchmarkPayload = json_encode(['text' => str_repeat('x', 256)]);
    for ($id = 100; $id < 10100; $id++) {
        $insert->execute([$id, 48, 'content.replied', null, null, null, $benchmarkPayload, 'benchmark-' . $id, null, '2026-11-27 10:00:00']);
    }
    $pdo->commit();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->query('ANALYZE TABLE notifications')->fetchAll();
    $plan = $pdo->query('EXPLAIN SELECT id,type,recipient_id,actor_id,resource_type,resource_id,read_at,created_at FROM notifications ORDER BY created_at DESC,id DESC LIMIT 20')->fetch(PDO::FETCH_ASSOC);
    $assert($plan['key'] === 'idx_notifications_created' && !str_contains((string) $plan['Extra'], 'filesort'), 'default first page must use the created_at/id index without filesort: ' . json_encode($plan));
    echo "PASS admin notification isolated database query, readonly projection and capability migration\n";
} finally {
    if (!preg_match('/^lovecards_e2e_notifications_[a-f0-9]{10}$/D', $database)) throw new LogicException('Unsafe test database name');
    $pdo->exec('DROP DATABASE `' . $database . '`');
}
