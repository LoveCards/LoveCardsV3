<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/topthink/framework/src/helper.php';

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Operations\Administration\Service\AdminReadModel;
use app\modules\Operations\Administration\Transport\Api\AdminValidate;
use app\modules\Operations\Dashboard\Service\Dashboard;
use app\modules\Platform\Files\Infrastructure\ThinkOrmFileRepository;
use app\modules\Platform\Files\Transport\Api\Files;
use app\modules\Platform\Safety\Infrastructure\ThinkDbAdminReportRepository;
use app\modules\Platform\Safety\Public\Safety;
use app\shared\Authorization\GrantSet;
use think\Container;
use think\DbManager;
use think\facade\Db;
use think\Model;

$app = new \think\App($root);
$lang = new \think\Lang($app, ['use_cookie' => false]);
\think\Validate::maker(static function (\think\Validate $validator) use ($lang): void { $validator->setLang($lang); });

final class NavigationQueryController extends BaseController
{
    public function input(string $validator, string $scene, array $input): array
    {
        return $this->param($validator, $validator::$all_scene[$scene], $input);
    }
}

$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$controller = (new ReflectionClass(NavigationQueryController::class))->newInstanceWithoutConstructor();
$valid = $controller->input(AdminValidate::class, 'reportList', ['status_group' => 'pending', 'resource_type' => 'card']);
$assert(($valid['status_group'] ?? null) === 'pending', 'pending report filter must survive transport validation');
foreach ([['status_group' => 'unknown'], ['status_group' => 'pending', 'status' => 'open']] as $invalid) {
    try {
        $controller->input(AdminValidate::class, 'reportList', $invalid);
        throw new RuntimeException('invalid or conflicting report filters must be rejected');
    } catch (ApiException $error) {
        $assert($error->getHttpStatus() === 400, 'invalid management query must use the existing validation response');
    }
}
foreach ([0, -1, '1.5', '1e2', 'invalid', '9007199254740992', [7]] as $invalid) {
    try {
        $controller->input(AdminValidate::class, 'eventList', ['event_id' => $invalid]);
        throw new RuntimeException('invalid event identifier must be rejected');
    } catch (ApiException $error) {
        $assert($error->getHttpStatus() === 400, 'invalid event identifier must be rejected at transport boundary');
    }
}
$valid = $controller->input(AdminValidate::class, 'eventList', ['event_id' => '9007199254740991']);
$assert((string) $valid['event_id'] === '9007199254740991', 'safe integer event identifier must survive validation');
$valid = $controller->input(Files::class, 'list', ['channel_slug' => 'archive & backup']);
$assert(($valid['channel_slug'] ?? null) === 'archive & backup', 'admin channel filter must retain exact slug');
$own = $controller->input(Files::class, 'listOwn', ['channel_slug' => 'local', 'page' => 1]);
$assert(!array_key_exists('channel_slug', $own), 'self-service file query must not gain the admin channel filter');
foreach ([['local'], str_repeat('x', 65)] as $invalid) {
    try {
        $controller->input(Files::class, 'list', ['channel_slug' => $invalid]);
        throw new RuntimeException('invalid channel filter must be rejected');
    } catch (ApiException $error) {
        $assert($error->getHttpStatus() === 400, 'invalid channel filter must use the existing validation response');
    }
}
$assert(!isset(Files::requestSchemas()['listOwn']['properties']['channel_slug']), 'own-list schema must remain unchanged');
$assert(isset(Files::requestSchemas()['list']['properties']['channel_slug']), 'admin-list schema must declare the exact channel filter');
fwrite(STDOUT, "PASS navigation query validation and admin-only contracts\n");

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDOUT, "SKIP isolated navigation repository queries (pdo_sqlite unavailable)\n");
    return;
}

// 内存连接完全独立于 dev 配置；实际仓库查询、分页和软删除范围都在该连接上验证。
$db = new DbManager();
$db->setConfig(['default' => 'navigation', 'connections' => ['navigation' => [
    'type' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'fields_strict' => false,
]]]);
Container::getInstance()->instance(DbManager::class, $db);
Model::setDb($db);

Db::execute('CREATE TABLE reports (id INTEGER PRIMARY KEY, reporter_id INTEGER, target_type TEXT, target_id TEXT, reason TEXT, details TEXT, status TEXT, assignee_id INTEGER, resolution TEXT, created_at TEXT, updated_at TEXT, resolved_at TEXT)');
foreach (['open', 'reviewing', 'resolved', 'rejected', 'closed'] as $index => $status) {
    Db::table('reports')->insert(['id' => $index + 1, 'status' => $status, 'target_type' => $index === 0 ? 'card' : 'comment', 'reason' => 'fixture', 'updated_at' => '2026-11-26 00:00:00']);
}
$reports = new ThinkDbAdminReportRepository();
$pending = $reports->list(['status_group' => 'pending'], 1, 20);
$ids = array_map('intval', array_column($pending['items'], 'id'));
sort($ids);
$assert($pending['total'] === 2 && $ids === [1, 2], 'pending group must include open and reviewing only');
$assert($reports->list(['status_group' => 'pending', 'resource_type' => 'card'], 1, 20)['total'] === 1, 'pending group must compose with target filter');
$assert($reports->list(['status_group' => 'pending'], 1, 1)['total'] === 2, 'pending count must cover all matching pages');
$assert(Safety::pendingReportStatuses() === ['open', 'reviewing'], 'Safety Public must expose the canonical pending set');
$tasks = (new ReflectionMethod(Dashboard::class, 'tasks'))->invoke(null, GrantSet::fromRows([
    ['capability' => 'reports.manage', 'scope' => 'global'],
]));
$assert(count($tasks) === 1 && $tasks[0]['count'] === $pending['total'], 'workbench pending report count must match landing list');
$assert($tasks[0]['action_url'] === '/apps/community/reports?status_group=pending', 'report task must land on the pending group');
$assert((new ReflectionMethod(Dashboard::class, 'tasks'))->invoke(null, new GrantSet([])) === [], 'report workbench task must remain permission filtered');

Db::execute('CREATE TABLE outbox_events (id INTEGER PRIMARY KEY, event_type TEXT, event_version INTEGER, aggregate_type TEXT, aggregate_key TEXT, occurred_at TEXT, created_at TEXT)');
Db::execute('CREATE TABLE event_deliveries (id INTEGER PRIMARY KEY, event_id INTEGER, handler_key TEXT, queue_name TEXT, status TEXT, attempt_count INTEGER, policy_decision TEXT, last_error TEXT)');
foreach ([7, 8] as $id) Db::table('outbox_events')->insert(['id' => $id, 'event_type' => 'message.sent', 'event_version' => 1]);
Db::table('event_deliveries')->insert(['id' => 19, 'event_id' => 7, 'handler_key' => 'notifications.message', 'queue_name' => 'notifications']);
$readModel = new AdminReadModel();
$event = $readModel->events(['event_id' => 7]);
$assert($event['total'] === 1 && (int) $event['data'][0]['id'] === 7, 'event identifier must be an exact primary-key filter');
$assert(count($event['data'][0]['deliveries']) === 1, 'source event must retain its related delivery tasks');
$assert($readModel->events(['event_id' => 999])['total'] === 0, 'missing event identifier must not fall back to all events');
$assert($readModel->events(['event_id' => 7, 'queue_name' => 'storage'])['total'] === 0, 'event identifier and queue must compose without broadening');
$assert($readModel->events(['event_id' => 8])['total'] === 1, 'events without handlers must remain discoverable by identifier');

Db::execute('CREATE TABLE files (id INTEGER PRIMARY KEY, user_id INTEGER, original_name TEXT, channel_slug TEXT, scene TEXT, file_size INTEGER, status INTEGER, upload_status INTEGER, is_public INTEGER, purge_status TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT)');
foreach ([
    ['id' => 1, 'channel_slug' => 'local', 'file_size' => 100, 'deleted_at' => null],
    ['id' => 2, 'channel_slug' => 'local-archive', 'file_size' => 200, 'deleted_at' => null],
    ['id' => 3, 'channel_slug' => 'local', 'file_size' => 300, 'deleted_at' => '2026-11-26 00:00:00'],
] as $row) Db::table('files')->insert($row + ['user_id' => 10, 'original_name' => 'fixture.jpg', 'scene' => 'card', 'status' => 0, 'upload_status' => 1, 'is_public' => 1]);
$files = new ThinkOrmFileRepository();
$normal = $files->paginate(['channel_slug' => 'local', 'page' => 1, 'list_rows' => 20], 10, true);
$assert($normal['total'] === 1 && (int) $normal['data'][0]['id'] === 1, 'channel filter must be exact and exclude recycled files by default');
$stats = $files->statsByChannel('local');
$assert((int) $stats['file_count'] === $normal['total'] && $stats['total_size'] === 100, 'channel statistics must use the same active-file range as the landing list');
$deleted = $files->paginate(['channel_slug' => 'local', 'show_deleted' => 2, 'page' => 1, 'list_rows' => 20], 10, true);
$assert($deleted['total'] === 1 && (int) $deleted['data'][0]['id'] === 3, 'recycle-bin navigation must retain the channel restriction');
$assert($files->paginate(['channel_slug' => "local' OR 1=1 --", 'page' => 1], 10, true)['total'] === 0, 'channel identifier must be bound as an exact value');
fwrite(STDOUT, "PASS isolated report tasks, source events and storage channel queries\n");
