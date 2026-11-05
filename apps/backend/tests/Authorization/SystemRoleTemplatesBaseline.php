<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/shared/Authorization/Scope.php';
require_once $root . '/app/shared/Authorization/Grant.php';
require_once $root . '/app/shared/Authorization/GrantSet.php';
require_once $root . '/app/modules/Platform/Rbac/Domain/CapabilityCatalog.php';
require_once $root . '/app/modules/Platform/Rbac/Domain/SystemRolePolicy.php';
require_once $root . '/app/modules/Operations/Dashboard/Domain/WorkbenchPolicy.php';

use app\modules\Operations\Dashboard\Domain\WorkbenchPolicy;
use app\modules\Platform\Rbac\Domain\CapabilityCatalog;
use app\modules\Platform\Rbac\Domain\SystemRolePolicy;
use app\shared\Authorization\GrantSet;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$grantKeys = static function (array $grants): array {
    $keys = array_map(
        static fn (array $grant): string => $grant['capability'] . ':' . $grant['scope'],
        $grants
    );
    sort($keys, SORT_STRING);
    return $keys;
};

$expected = [
    'content_operator' => [
        'admin.access:global', 'dashboard.read:global',
        'cards.read:all', 'cards.create:global', 'cards.update:all', 'cards.delete:all',
        'tags.read:all', 'tags.create:global', 'tags.update:all', 'tags.delete:all',
        'files.upload:global',
    ],
    'community_operator' => [
        'admin.access:global', 'dashboard.read:global',
        'comments.read:all', 'comments.update:all', 'comments.pin:all', 'comments.delete:all',
        'reports.read:global', 'reports.manage:global', 'community.insights.read:global',
    ],
    'moderator' => [
        'admin.access:global', 'dashboard.read:global',
        'cards.read:all', 'cards.approve:all',
        'comments.read:all', 'comments.moderate:all',
        'reports.read:global', 'reports.manage:global',
    ],
    'security_admin' => [
        'admin.access:global', 'dashboard.read:global',
        'users.read:all', 'users.update:all',
        'users.security.read:global', 'users.sessions.revoke:global',
        'users.recovery.read:global', 'audit.read:global',
    ],
    'operations_admin' => [
        'admin.access:global', 'dashboard.read:global',
        'operations.read:global', 'operations.migrate:global', 'operations.retry:global',
        // Phase 1 通信运行时控制面：运维管理员可变更模式与运行参数（敏感，走审计）。
        'operations.runtime.configure:global',
        'communications.read:global', 'audit.read:global',
        'system.update:global', 'system.update.contract:global', 'system.recover:global',
        'config.read:global', 'config.update:global',
        'storage.read:global', 'storage.install:global', 'storage.test:global',
        'sender.read:global', 'sender.install:global', 'sender.test:global',
        'captcha.read:global', 'captcha.install:global',
        'files.read:all', 'files.update:all', 'files.delete:all',
        'files.restore:all', 'files.purge:all', 'files.cleanup:global', 'files.usage.read:global',
    ],
    'auditor' => [
        'admin.access:global', 'dashboard.read:global',
        'cards.read:all', 'comments.read:all', 'tags.read:all',
        'reports.read:global', 'community.insights.read:global',
        'users.read:all', 'users.security.read:global', 'users.recovery.read:global',
        'config.read:global', 'storage.read:global', 'sender.read:global',
        'captcha.read:global', 'theme.read:global',
        'files.read:all', 'files.usage.read:global',
        'operations.read:global', 'communications.read:global', 'audit.read:global',
    ],
];
$names = [
    'content_operator' => '内容运营',
    'community_operator' => '社区运营',
    'moderator' => '审核员',
    'security_admin' => '安全管理员',
    'operations_admin' => '运维管理员',
    'auditor' => '审计员',
];

$templates = SystemRolePolicy::administrationTemplates();
$assert(array_keys($templates) === array_keys($expected), 'administration template slugs must be stable and complete');
foreach ($templates as $slug => $template) {
    $assert($template['name'] === $names[$slug], "{$slug} must keep its Chinese name");
    $actual = $grantKeys($template['grants']);
    $wanted = $expected[$slug];
    sort($wanted, SORT_STRING);
    $assert($actual === $wanted, "{$slug} must keep its exact minimum grant set");
    $assert(count($actual) === count(array_unique($actual)), "{$slug} grants must not contain duplicates");

    foreach ($template['grants'] as $grant) {
        $capability = $grant['capability'];
        $scope = $grant['scope'];
        $assert(array_key_exists($capability, CapabilityCatalog::canonical()), "{$slug} references unknown capability {$capability}");
        $assert(in_array($scope, CapabilityCatalog::scopesFor($capability), true), "{$slug} uses unsupported scope {$capability}:{$scope}");
    }
}

$auditorGrants = $grantKeys($templates['auditor']['grants']);
foreach ($auditorGrants as $grant) {
    [$capability] = explode(':', $grant, 2);
    $assert(
        !preg_match('/\.(?:create|update|delete|manage|assign|install|test|upload|activate|migrate|retry|recover|revoke|cleanup|purge)$/', $capability),
        "auditor must remain read-only: {$capability}"
    );
}
$assert(!in_array('comments.delete:all', $grantKeys($templates['moderator']['grants']), true), 'moderator must not delete comments');
$assert(!in_array('cards.update:all', $grantKeys($templates['moderator']['grants']), true), 'moderator must not edit card content');
$assert(!in_array('users.roles.assign:global', $grantKeys($templates['security_admin']['grants']), true), 'security admin must not assign roles');
$assert(!in_array('roles.assign:global', $grantKeys($templates['operations_admin']['grants']), true), 'operations admin must not administer RBAC');

$dataSql = file_get_contents($root . '/data.sql');
$migration = file_get_contents($root . '/database/migrations/20260823000001.sql');
// 模板角色授权允许通过后续迁移差分工单追加（历史种子 checksum 不可改写）。
$migrationDelta = file_get_contents($root . '/database/migrations/20261106000004.sql');
$rolesService = file_get_contents($root . '/app/modules/Platform/Rbac/Service/Roles.php');
$assert($dataSql !== false && $migration !== false && $rolesService !== false, 'role template seed sources must be readable');
foreach ($names as $slug => $name) {
    $assert(str_contains($dataSql, "'{$name}', '{$slug}', 1"), "clean install must create {$slug} as a system role");
    $assert(str_contains($migration, "'{$slug}'"), "upgrade migration must include {$slug}");
}
$assert(str_contains($dataSql, "(1, '超级管理员', 'root', 1"), 'root ID anchor must remain 1');
$assert(str_contains($dataSql, "(2, '管理员', 'admin', 1"), 'admin ID anchor must remain 2');
$assert(str_contains($dataSql, "(3, '用户', 'user', 1"), 'user ID anchor must remain 3');
$assert(str_contains($dataSql, "(4, '访客', 'guest', 1"), 'guest ID anchor must remain 4');
$assert(!str_contains($migration, '`user_roles`'), 'upgrade migration must not assign templates to users');
$assert(str_contains($migration, 'LEFT JOIN `roles` existing') && str_contains($migration, 'WHERE existing.`id` IS NULL'), 'role migration must be idempotent');
$assert(str_contains($migration, 'UPDATE `roles` AS target') && str_contains($migration, 'NOT (target.`description` <=> template.`description`)'), 'upgrade migration must normalize stable template names without rewriting unchanged rows');
$assert(str_contains($migration, 'LEFT JOIN `role_grants` existing') && str_contains($migration, 'WHERE existing.`role_id` IS NULL'), 'grant migration must be idempotent');
$assert(str_contains($rolesService, 'ensureAdministrationTemplates()'), 'clean install seeding must create template roles by slug');
$assert(str_contains($rolesService, '只有超级管理员才能修改系统角色'), 'service layer must protect every system role from non-root mutation');

preg_match_all(
    "/\('([^']+)',\s*'([^']+)',\s*'(global|all|own)'\)/",
    $migration,
    $matches,
    PREG_SET_ORDER
);
$migrationGrants = [];
foreach ($matches as $match) {
    if (!isset($expected[$match[1]])) continue;
    $migrationGrants[$match[1]][] = $match[2] . ':' . $match[3];
}
// 差分工单（20261106 起）：模板新增授权按 slug/capability/scope 三元组追加。
preg_match_all(
    "/SELECT '([a-z_]+)' AS role_slug, '([a-z0-9_.]+)' AS capability, '([a-z]+)' AS scope/",
    $migrationDelta,
    $deltaMatches,
    PREG_SET_ORDER
);
foreach ($deltaMatches as $match) {
    if (!isset($expected[$match[1]])) continue;
    $migrationGrants[$match[1]][] = $match[2] . ':' . $match[3];
}
foreach ($expected as $slug => $wanted) {
    $actual = $migrationGrants[$slug] ?? [];
    sort($actual, SORT_STRING);
    sort($wanted, SORT_STRING);
    $assert($actual === $wanted, "migration grants for {$slug} must match the code-owned template exactly");
}

$expectedTasks = [
    'content_operator' => [],
    'community_operator' => ['reports_open'],
    'moderator' => ['cards_pending', 'comments_pending', 'reports_open'],
    'security_admin' => ['recovery_active'],
    'operations_admin' => ['deliveries_dead', 'files_pending'],
    'auditor' => [],
];
$expectedSummaries = [
    'content_operator' => ['cards', 'good'],
    'community_operator' => ['comments'],
    'moderator' => ['cards', 'good', 'comments'],
    'security_admin' => [],
    'operations_admin' => [],
    'auditor' => ['cards', 'good', 'comments'],
];
foreach ($templates as $slug => $template) {
    $grants = GrantSet::fromRows($template['grants']);
    $assert(
        WorkbenchPolicy::taskKeys($grants) === $expectedTasks[$slug],
        "{$slug} workbench tasks must derive only from its real grants"
    );
    $assert(
        WorkbenchPolicy::summaryKeys($grants) === $expectedSummaries[$slug],
        "{$slug} dashboard summaries must derive only from its real grants"
    );
}

fwrite(STDOUT, "PASS administration role templates baseline\n");
