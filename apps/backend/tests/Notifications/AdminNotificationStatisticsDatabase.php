<?php
declare(strict_types=1);

use app\modules\Operations\Administration\Service\AdminReadModel;
use app\modules\Operations\SystemLifecycle\Infrastructure\MigrationCatalog;
use app\modules\Platform\Notifications\Public\AdminNotifications;
use think\facade\Db;

$host = getenv('LC_TEST_DB_HOST');
if (!is_string($host) || $host === '') { echo "SKIP notification statistics database integration (LC_TEST_DB_HOST not configured)\n"; return; }
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) throw new RuntimeException('Statistics tests require a local disposable database');
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$database = 'lovecards_e2e_notice_statistics_' . bin2hex(random_bytes(5));
$port = getenv('LC_TEST_DB_PORT') ?: '33309'; $username = getenv('LC_TEST_DB_USER') ?: 'root'; $password = getenv('LC_TEST_DB_PASSWORD') ?: '';
$pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$temporary = sys_get_temp_dir() . '/' . $database;
$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
try {
    $pdo->exec("USE `{$database}`");
    $schema = (string) file_get_contents($root . '/database/schema.sql');
    foreach (['notifications', 'capabilities', 'role_grants'] as $table) {
        if (preg_match('/CREATE TABLE `' . $table . '` \([\s\S]*?\) ENGINE=[^;]+;/u', $schema, $match) !== 1) throw new RuntimeException('Missing fixture table ' . $table);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0'); $pdo->exec($match[0]); $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    foreach (['HOSTNAME' => $host, 'HOSTPORT' => $port, 'DATABASE' => $database, 'USERNAME' => $username, 'PASSWORD' => $password, 'COLLATION' => 'utf8mb4_general_ci'] as $key => $value) putenv('PHP_DATABASE_' . $key . '=' . $value);
    putenv('LC_INSTANCE_ROOT=' . $temporary . '/instance'); putenv('LC_INSTANCE_ROOT_RESOLVED=' . $temporary . '/instance');
    $app = new \think\App($root); $app->setEnvName('notification-statistics-e2e'); $app->setRuntimePath($temporary . '/runtime/');
    (new ReflectionProperty($app, 'initializers'))->setValue($app, [\think\initializer\RegisterService::class, \think\initializer\BootService::class]);
    $app->initialize(); restore_exception_handler();
    $assert(Db::query('SELECT DATABASE() AS name')[0]['name'] === $database, 'statistics queries must stay inside the disposable database');
    $assert(AdminNotifications::stats() === ['total' => 0, 'unread' => 0, 'recipients' => 0, 'types' => []], 'empty statistics return real integer zero counts');
    $insert = $pdo->prepare('INSERT INTO notifications (id,recipient_id,type,actor_id,resource_type,resource_id,payload,dedupe_key,read_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ([
        [1, 48, 'content.commented', 7, 'card', '11', '{"message":"private body"}', 'dedupe-1', null, '2026-11-26 23:59:59'],
        [2, 48, 'content.commented', 7, 'card', '12', '{"message":"private body"}', 'dedupe-2', '2026-11-27 09:00:00', '2026-11-27 08:00:00'],
        [3, 52, 'system.notice', null, null, null, '{"message":"private system body"}', 'dedupe-3', null, '2026-11-27 08:00:00'],
        [4, 52, 'legacy.unknown', null, null, null, '{"message":"private legacy body"}', 'dedupe-4', '2026-11-27 10:00:00', '2026-11-27 08:00:00'],
    ] as $row) $insert->execute($row);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $before = $pdo->query('SELECT * FROM notifications ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $stats = AdminNotifications::stats();
    $assert($stats['total'] === 4 && $stats['unread'] === 2 && $stats['recipients'] === 2, 'statistics count persisted records, unread status and distinct recipient identities');
    $byType = array_column($stats['types'], 'count', 'type');
    $assert($byType['content.commented'] === 2 && $byType['system.notice'] === 1 && $byType['legacy.unknown'] === 1, 'type statistics retain unknown historical types');
    $assert(array_sum($byType) === 4, 'type counts agree with the global count');
    $assert((new AdminReadModel())->notificationsStats() === $stats, 'existing operations URL shares the same Notifications-owned aggregate result');
    $assert($pdo->query('SELECT * FROM notifications ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $before, 'statistics never change read state, contents or notification count');
    $assert(!str_contains(json_encode($stats), 'private') && !array_key_exists('payload', $stats) && !array_key_exists('dedupe_key', $stats), 'statistics expose no private body or dedupe data');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec("INSERT INTO role_grants(role_id,capability,scope,created_at) VALUES(2,'admin.access','global','2026-11-27 00:00:00')");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $grantsBefore = $pdo->query('SELECT * FROM role_grants ORDER BY role_id,capability,scope')->fetchAll(PDO::FETCH_ASSOC);
    $migration = null;
    foreach ((new MigrationCatalog())->discover($root . '/database/migrations') as $candidate) if ($candidate->descriptor()->id() === '20261127000001') $migration = $candidate;
    $assert($migration !== null, 'existing additive notification permission/index migration remains available');
    $migration->preflight(); $migration->expand(); $migration->expand(); $migration->verify();
    $assert($pdo->query('SELECT * FROM role_grants ORDER BY role_id,capability,scope')->fetchAll(PDO::FETCH_ASSOC) === $grantsBefore, 'retained aggregate migration cannot expand role grants');
    echo "PASS notification statistics isolated database aggregation and readonly behavior\n";
} finally {
    if (preg_match('/^lovecards_e2e_notice_statistics_[a-f0-9]{10}$/D', $database) !== 1) throw new RuntimeException('Unsafe test database cleanup');
    $pdo->exec("DROP DATABASE `{$database}`");
}
