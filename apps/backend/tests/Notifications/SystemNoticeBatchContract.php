<?php
declare(strict_types=1);

use app\api\ApiException;
use app\api\controller\BaseController;
use app\api\middleware\PermissionCheck;
use app\modules\Platform\Auth\Public\AuthContext;
use app\modules\Platform\Notifications\Contract\NotificationTypeCatalog;
use app\modules\Platform\Notifications\Transport\Api\NotificationCursorPageResource;
use app\modules\Platform\Notifications\Transport\Api\SystemNoticeBatchResource;
use app\modules\Platform\Notifications\Transport\Api\SystemNoticeBatchValidate;
use app\modules\Platform\Notifications\Transport\Api\SystemNoticeLatestBatchResource;
use app\modules\Platform\Notifications\Transport\Api\SystemNoticeRecipientResource;
use app\modules\Platform\Rbac\Domain\CapabilityCatalog;
use app\modules\Platform\Rbac\Domain\SystemRolePolicy;
use app\modules\Platform\Rbac\Service\RBAC;
use app\shared\Authorization\GrantSet;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$temporary = sys_get_temp_dir() . '/lovecards_notice_contract_' . bin2hex(random_bytes(5));
putenv('LC_INSTANCE_ROOT=' . $temporary . '/instance');
putenv('LC_INSTANCE_ROOT_RESOLVED=' . $temporary . '/instance');
$app = new \think\App($root);
$app->setEnvName('system-notice-contract');
$app->setRuntimePath($temporary . '/runtime/');
(new ReflectionProperty($app, 'initializers'))->setValue($app, [\think\initializer\RegisterService::class, \think\initializer\BootService::class]);
$app->initialize();
restore_exception_handler();
$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$boundary = new class extends BaseController {
    public function parse(string $scene, array $input): array
    {
        return $this->param(SystemNoticeBatchValidate::class, SystemNoticeBatchValidate::$all_scene[$scene], $input);
    }
};
$reject = static function (string $scene, array $input) use ($boundary, $assert): void {
    try { $boundary->parse($scene, $input); }
    catch (ApiException $error) { $assert($error->getHttpStatus() === 422, 'invalid publishing input must return HTTP 422'); return; }
    throw new RuntimeException('Invalid input unexpectedly passed: ' . $scene . ' ' . json_encode($input));
};

$base = ['request_key' => 'contract:prepare-1', 'audience' => 'selected', 'recipient_ids' => [48, 52], 'message' => '系统维护通知', 'action_url' => '/settings', 'severity' => 'info'];
$parsed = $boundary->parse('prepare', $base + ['actor_id' => 9001, 'type' => 'system.security', 'payload' => ['private' => true]]);
$assert($parsed === $base, 'transport must discard actor, notification type and arbitrary payload instead of accepting a generic sending channel');
$assert($boundary->parse('prepare', array_diff_key($base, array_flip(['recipient_ids', 'action_url', 'severity'])))['audience'] === 'selected', 'optional fields remain absent until domain normalization');
$assert($boundary->parse('prepare', array_replace($base, ['audience' => 'all', 'recipient_ids' => [], 'action_url' => null, 'severity' => 'warning']))['action_url'] === null, 'nullable internal action path must remain null');
$assert($boundary->parse('confirm', ['actor_id' => 9, 'message' => 'cannot overwrite frozen content']) === [], 'confirmation must not accept client content, audience or actor');
$assert($boundary->parse('latest', []) === [], 'latest lookup without request key remains supported');
$assert($boundary->parse('latest', ['request_key' => 'contract:prepare-1']) === ['request_key' => 'contract:prepare-1'], 'exact request key recovery must remain a formal query');
$assert($boundary->parse('retry', ['expected_generation' => 1, 'reason' => '基础故障已恢复']) === ['expected_generation' => 1, 'reason' => '基础故障已恢复'], 'retry carries the approved generation and reason');
$assert($boundary->parse('candidates', ['query' => '#48', 'limit' => '10'])['query'] === '#48', 'candidate search supports a numeric user reference');
foreach ([
    ['request_key' => ''], ['request_key' => null], ['request_key' => str_repeat('x', 65)],
    ['audience' => 'role'], ['message' => ' '], ['message' => []], ['message' => "invalid\0body"],
    ['message' => str_repeat('汉', 1500)], ['recipient_ids' => ['48']], ['recipient_ids' => [0]],
    ['recipient_ids' => [2147483648]], ['recipient_ids' => range(1, 201)], ['recipient_ids' => null],
    ['severity' => 'critical'], ['severity' => null], ['action_url' => '//outside.example'],
    ['action_url' => 'https://outside.example'], ['action_url' => '/%2foutside.example'],
    ['action_url' => '/%5coutside.example'], ['action_url' => "/settings\n"],
] as $change) $reject('prepare', array_replace($base, $change));
foreach ([['query' => ''], ['query' => str_repeat('汉', 81)], ['query' => ['48']], ['query' => '48', 'limit' => 0], ['query' => '48', 'limit' => '1e1'], ['query' => '48', 'limit' => 21]] as $input) $reject('candidates', $input);
foreach ([[], ['expected_generation' => 0, 'reason' => '测试'], ['expected_generation' => '1', 'reason' => '测试'], ['expected_generation' => 1, 'reason' => ' '], ['expected_generation' => 1, 'reason' => str_repeat('字', 256)]] as $input) $reject('retry', $input);
$reject('latest', ['request_key' => null]);

$fixture = [
    'id' => '71', 'request_key' => 'contract:prepare-1', 'generation' => '0', 'audience' => 'selected',
    'recipient_ids' => ['48'], 'recipients' => [['id' => '48', 'username' => '朝夕', 'email' => 'private@example.test']],
    'message' => '系统维护通知', 'action_url' => null, 'severity' => 'info', 'status' => 'prepared',
    'recipient_count' => '1', 'generated_count' => '0', 'skipped_count' => '0', 'pending_count' => '1', 'failed_count' => '0',
    'created_at' => '2026-11-27 08:00:00', 'confirmed_at' => null, 'completed_at' => null,
    'can_confirm' => true, 'can_retry' => false, 'diagnostic_event_id' => null,
    'creator_private_profile' => ['email' => 'private@example.test'], 'request_hash' => 'internal', 'retry_reason' => 'private internal note',
];
$item = SystemNoticeBatchResource::make($fixture);
$assert($item['id'] === 71 && $item['generation'] === 0 && $item['recipient_count'] === 1 && $item['pending_count'] === 1, 'batch identity and counts must be typed integers');
$assert($item['action_url'] === null && $item['confirmed_at'] === null && $item['completed_at'] === null && $item['diagnostic_event_id'] === null, 'not-yet-published receipt must preserve null facts');
$declared = SystemNoticeBatchResource::schema()['required']; $actual = array_keys($item); sort($declared); sort($actual);
$assert($actual === $declared, 'batch resource must expose exactly the declared response fields');
$assert($item['recipients'] === [['id' => 48, 'username' => '朝夕']] && $item['recipient_ids'] === [48], 'recipient resource must expose only numeric id and username');
$assert(!isset($item['request_hash'], $item['creator_private_profile'], $item['retry_reason']), 'batch responses must not expose internal dedupe, retry or account data');
$assert(SystemNoticeRecipientResource::make(['id' => '52', 'username' => '山河', 'phone' => 'private', 'avatar' => 'private']) === ['id' => 52, 'username' => '山河'], 'candidate response is a dedicated minimal projection');
$assert(SystemNoticeLatestBatchResource::make(null) === ['batch' => null], 'no previous publication is an explicit nullable result');
$assert(SystemNoticeLatestBatchResource::make($fixture) === ['batch' => $item], 'latest and direct reads share one batch projection');

$ordinary = NotificationCursorPageResource::make(['data' => [], 'next_cursor' => null, 'has_more' => false]);
$assert($ordinary === ['data' => [], 'next_cursor' => null, 'has_more' => false], 'user notification cursor response remains unchanged');
foreach (NotificationTypeCatalog::types() as $type) $assert(NotificationTypeCatalog::labelFor($type) !== '未知类型', 'existing user notification types must retain their labels');

$app->route->clear(); $app->route->lazy(false);
require $root . '/app/api/route/notifications.php';
$routes = $app->route->getRuleList();
$expectedNames = ['notifications.recipientCandidates', 'notifications.latestBatch', 'notifications.prepareBatch', 'notifications.getBatch', 'notifications.confirmBatch', 'notifications.retryBatch'];
$names = array_column($routes, 'name'); sort($names); sort($expectedNames);
$assert($names === $expectedNames, 'admin module must expose only the six reviewed publication routes');
$assert(!in_array('notifications.listManaged', $names, true) && !in_array('notifications.getManaged', $names, true), 'ordinary per-recipient administrator list and detail routes are retired');
$required = [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'notifications.publish', 'scope' => 'global']];
$middleware = new PermissionCheck();
foreach ($routes as $route) {
    $access = $route['option']['meta']['access'];
    $assert($access === ['auth' => 'required', 'allOf' => $required], 'all publishing routes require explicit administrator and publishing capabilities');
    $probe = $app->route->get('publication-permission-probe', static fn() => 'unused')->setOption('meta', ['access' => $access]);
    $app->request->setRule($probe);
    foreach ([[], [$required[0]], [$required[1]], [$required[0], ['capability' => 'communications.read', 'scope' => 'global']], [$required[0], ['capability' => 'notifications.read', 'scope' => 'global']], [$required[0], ['capability' => 'notifications.broadcast', 'scope' => 'global']]] as $grants) {
        $app->request->auth = AuthContext::authenticated(1, null, [], GrantSet::fromRows($grants));
        $result = $middleware->handle($app->request, static fn() => 'allowed');
        $assert($result instanceof \think\Response && $result->getCode() === 403, 'aggregate readers or broadcast-only roles cannot access publication or recipient data');
    }
    $app->request->auth = AuthContext::authenticated(1, null, [], GrantSet::fromRows($required));
    $assert($middleware->handle($app->request, static fn() => 'allowed') === 'allowed', 'publisher reaches the owner-scoped domain use case');
}
$matrix = SystemRolePolicy::capabilityMatrix(['root' => 1, 'admin' => 2, 'user' => 3, 'guest' => 4]);
foreach (['notifications.publish', 'notifications.broadcast'] as $capability) {
    $assert(isset(CapabilityCatalog::all()[$capability]), 'publishing capability must be registered');
    foreach ($matrix as $caps) $assert(!in_array($capability, $caps, true), 'publishing is never silently assigned to an existing role');
    foreach (SystemRolePolicy::administrationTemplates() as $template) $assert(!in_array($capability, array_column($template['grants'], 'capability'), true), 'existing role templates keep their minimum grants');
    $rootGrants = (new ReflectionMethod(RBAC::class, 'rootGrantSet'))->invoke(null);
    $assert($rootGrants->allows($capability, 'global'), 'root receives current capability catalog dynamically');
}
echo "PASS system notice publishing validation, projection and authorization contract\n";
