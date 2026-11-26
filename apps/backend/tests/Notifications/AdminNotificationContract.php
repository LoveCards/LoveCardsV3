<?php
declare(strict_types=1);

use app\api\ApiException;
use app\api\controller\BaseController;
use app\api\middleware\PermissionCheck;
use app\modules\Platform\Auth\Public\AuthContext;
use app\modules\Platform\Notifications\Contract\NotificationTypeCatalog;
use app\modules\Platform\Notifications\Transport\Api\AdminNotificationQueryValidate;
use app\modules\Platform\Notifications\Transport\Api\AdminNotificationResource;
use app\modules\Platform\Rbac\Domain\CapabilityCatalog;
use app\modules\Platform\Rbac\Domain\SystemRolePolicy;
use app\modules\Platform\Rbac\Service\RBAC;
use app\shared\Authorization\GrantSet;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
putenv('LC_INSTANCE_ROOT=' . sys_get_temp_dir() . '/lovecards_notification_contract_instance');
putenv('LC_INSTANCE_ROOT_RESOLVED=' . sys_get_temp_dir() . '/lovecards_notification_contract_instance');
$app = new \think\App($root);
$app->setEnvName('notification-contract');
$app->setRuntimePath(sys_get_temp_dir() . '/lovecards_notification_contract_runtime/');
$initializers = new ReflectionProperty($app, 'initializers');
$initializers->setValue($app, [\think\initializer\RegisterService::class, \think\initializer\BootService::class]);
$app->initialize();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$fixture = [
    'id' => '37', 'type' => 'content.replied', 'recipient_id' => '48', 'actor_id' => null,
    'resource_type' => 'comment', 'resource_id' => '19', 'read_at' => null, 'created_at' => '2026-11-27 08:00:00',
    'payload' => ['body' => 'private'], 'dedupe_key' => 'private', 'preferences' => ['private' => true],
];
$item = AdminNotificationResource::make($fixture);
$assert($item['id'] === 37 && $item['recipient_id'] === 48 && $item['actor_id'] === null, 'metadata identity casts and nullable fields must be stable');
$assert($item['type_label'] === '评论回复' && $item['created_at'] === $fixture['created_at'], 'resource must preserve stored timestamps and label known types');
$assert(array_keys($item) === AdminNotificationResource::schema()['required'], 'resource must expose only its declared fields');
$assert(!isset($item['payload'], $item['dedupe_key'], $item['preferences']), 'metadata must not expose notification body or delivery internals');
$unknown = AdminNotificationResource::make(array_replace($fixture, ['type' => 'legacy.unknown']));
$assert($unknown['type'] === 'legacy.unknown' && $unknown['type_label'] === '未知类型', 'unknown stored types must remain inspectable');
$page = AdminNotificationResource::paginate(['data' => [$fixture], 'total' => 1, 'per_page' => 20, 'current_page' => 1, 'last_page' => 1]);
$assert($page['data'] === [$item], 'list and detail must share one metadata projection');
foreach (NotificationTypeCatalog::types() as $type) {
    $assert(NotificationTypeCatalog::labelFor($type) !== '未知类型', 'every registered type must have a presentation label');
}

$valid = [
    [], ['page' => '1', 'list_rows' => '100', 'recipient_id' => 48],
    ['type' => 'legacy.unknown', 'read_state' => 'unread'],
    ['from' => '2024-02-29 00:00:00', 'to' => '2024-03-01 00:00:00'],
    ['from' => '2026-11-27 08:00:00'], ['to' => '2026-11-28 00:00:00'],
];
foreach ($valid as $params) {
    $validator = new AdminNotificationQueryValidate();
    $assert($validator->check($params), 'valid optional filters must pass: ' . json_encode($params));
}
$invalid = [
    ['page' => '1.2'], ['page' => '1e2'], ['page' => 0], ['page' => null], ['page' => ''],
    ['list_rows' => 101], ['list_rows' => false], ['recipient_id' => -1], ['recipient_id' => ['48']],
    ['type' => []], ['type' => ''], ['type' => str_repeat('a', 65)], ['read_state' => 'all'],
    ['from' => '2026-02-30 00:00:00'], ['from' => '2026-11-27T08:00:00Z'],
    ['from' => '2026-11-27 24:00:00'], ['from' => '2026-11-27 08:00:00', 'to' => '2026-11-27 08:00:00'],
    ['from' => '2026-11-28 00:00:00', 'to' => '2026-11-27 00:00:00'], ['to' => null],
];
foreach ($invalid as $params) {
    $validator = new AdminNotificationQueryValidate();
    $assert(!$validator->check($params), 'invalid filter must fail: ' . json_encode($params));
}
$boundary = new class extends BaseController {
    public function query(array $params): array
    {
        return $this->param(AdminNotificationQueryValidate::class, AdminNotificationQueryValidate::$all_scene['list'], $params);
    }
};
try {
    $boundary->query(['page' => '1.5']);
    throw new RuntimeException('invalid query must throw a structured validation error');
} catch (ApiException $error) {
    $assert($error->getHttpStatus() === 422, 'new query validation must return HTTP 422');
}

$app->route->clear();
$app->route->lazy(false);
require $root . '/app/api/route/notifications.php';
$routes = $app->route->getRuleList();
$assert(count($routes) === 2, 'admin notification slice must only expose two GET routes');
$expected = [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'notifications.read', 'scope' => 'global']];
$request = $app->request;
$middleware = new PermissionCheck();
foreach ($routes as $route) {
    $assert(strtolower((string) $route['method']) === 'get', 'notifications admin routes must remain read-only');
    $access = $route['option']['meta']['access'];
    $assert($access === ['auth' => 'required', 'allOf' => $expected], 'both routes must require independent admin and notifications grants');
    $rule = $app->route->get('notification-permission-probe', static fn() => 'unused')->setOption('meta', ['access' => $access]);
    $request->setRule($rule);
    foreach ([[], [$expected[0]], [$expected[1]], [$expected[0], ['capability' => 'communications.read', 'scope' => 'global']], [$expected[0], ['capability' => 'notifications.read', 'scope' => 'own']]] as $grants) {
        $request->auth = AuthContext::authenticated(1, null, [], GrantSet::fromRows($grants));
        $result = $middleware->handle($request, static fn() => 'allowed');
        $assert($result instanceof \think\Response && $result->getCode() === 403, 'missing or legacy communications grants must not read notification rows');
    }
    $request->auth = AuthContext::authenticated(1, null, [], GrantSet::fromRows($expected));
    $assert($middleware->handle($request, static fn() => 'allowed') === 'allowed', 'explicit metadata reader must pass the same middleware');
}
$assert(isset(CapabilityCatalog::all()['notifications.read']), 'dedicated capability must be registered');
$matrix = SystemRolePolicy::capabilityMatrix(['root' => 1, 'admin' => 2, 'user' => 3, 'guest' => 4]);
foreach ($matrix as $capabilities) {
    $assert(!in_array('notifications.read', $capabilities, true), 'new capability must not be silently assigned to a default non-root role');
}
foreach (SystemRolePolicy::administrationTemplates() as $template) {
    $assert(!in_array('notifications.read', array_column($template['grants'], 'capability'), true), 'existing templates must retain their previous minimum privilege');
}
$rootGrants = (new ReflectionMethod(RBAC::class, 'rootGrantSet'))->invoke(null);
$assert($rootGrants->allows('notifications.read', 'global'), 'root must retain dynamic full-catalog access without database role mutation');
echo "PASS admin notification resource, validation and route authorization contract\n";
