<?php
declare(strict_types=1);

use think\facade\Route;
use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\modules\Operations\Administration\Transport\Api\AdminValidate;
use app\modules\Operations\Administration\Transport\Api\AdminOverviewResource;
use app\modules\Operations\Administration\Transport\Api\AdminMigrationResource;
use app\modules\Operations\Administration\Transport\Api\AdminRuntimeResource;
use app\modules\Operations\Administration\Transport\Api\AdminRuntimeConfigResource;
use app\modules\Operations\Administration\Transport\Api\AdminRuntimeValidateResource;
use app\modules\Operations\Administration\Transport\Api\AdminDeliveryResource;
use app\modules\Operations\Administration\Transport\Api\AdminAuditLogResource;
use app\modules\Operations\Administration\Transport\Api\AdminNotificationStatsResource;
use app\modules\Operations\Administration\Transport\Api\AdminCommunityInsightsResource;
use app\modules\Platform\Safety\Transport\Api\AdminReportResource;
use app\modules\Platform\Auth\Transport\Api\AdminSecurityResource;
use app\modules\Platform\Auth\Transport\Api\AdminSessionResource;
use app\modules\Platform\Auth\Transport\Api\AdminRecoveryChallengeResource;
use app\modules\Platform\Files\Transport\Api\AdminFileUsageResource;
use app\modules\Operations\Administration\Transport\Api\AdminUpgradePlanResource;
use app\shared\Authorization\Scope;

$access = static fn (string $capability): array => ['auth' => 'required', 'allOf' => [
    ['capability' => 'admin.access', 'scope' => Scope::GLOBAL],
    ['capability' => $capability, 'scope' => Scope::GLOBAL],
]];
$response = static fn (?string $resource, string $shape = 'item', int $status = 200): array => compact('resource', 'shape', 'status');
$request = static fn (string $scene): array => ['validate' => AdminValidate::class, 'scene' => $scene];

Route::group('admin/operations', function () use ($access, $response, $request) {
    Route::get('overview', 'Platform.Administration/overview')->name('operations.overview')
        ->setOption('contract', ['request' => null, 'response' => $response(AdminOverviewResource::class)])
        ->setOption('meta', ['name' => '运维总览', 'group' => '运维', 'access' => $access('operations.read')]);
    Route::get('migrations', 'Platform.Administration/migrations')->name('operations.migrations')
        ->setOption('contract', ['request' => null, 'response' => $response(AdminMigrationResource::class, 'collection')])
        ->setOption('meta', ['name' => '迁移计划', 'group' => '运维', 'access' => $access('operations.read')]);
    Route::post('migrations/run', 'Platform.Administration/runMigrations')->name('operations.runMigrations')
        ->setOption('contract', ['request' => array_merge(
            $request('migrationRun'),
            [
                'rejectTruthy' => [
                    'allow_contract' => 'Contract 迁移必须通过已激活且完成备份 Gate 的升级计划审批',
                ],
            ],
        ), 'response' => $response(AdminMigrationResource::class, 'collection')])
        ->setOption('meta', ['name' => '推进迁移', 'group' => '运维', 'access' => $access('operations.migrate')]);
    Route::get('runtime', 'Platform.Administration/runtime')->name('operations.runtime')
        ->setOption('contract', ['request' => null, 'response' => $response(AdminRuntimeResource::class)])
        ->setOption('meta', ['name' => '运行环境', 'group' => '运维', 'access' => $access('operations.read')]);
    Route::get('runtime/config', 'Platform.Administration/runtimeConfig')->name('operations.runtimeConfig')
        ->setOption('contract', ['request' => null, 'response' => $response(AdminRuntimeConfigResource::class)])
        ->setOption('meta', ['name' => '运行时通信配置', 'group' => '运维', 'access' => $access('operations.read')]);
    Route::post('runtime/validate', 'Platform.Administration/validateRuntimeConfig')->name('operations.validateRuntimeConfig')
        ->setOption('contract', ['request' => $request('runtimeConfig'), 'response' => $response(AdminRuntimeValidateResource::class)])
        ->setOption('meta', ['name' => '校验运行时配置', 'group' => '运维', 'access' => $access('operations.runtime.configure')]);
    Route::put('runtime/config', 'Platform.Administration/applyRuntimeConfig')->name('operations.applyRuntimeConfig')
        ->setOption('contract', ['request' => $request('runtimeConfig'), 'response' => $response(AdminRuntimeConfigResource::class)])
        ->setOption('meta', ['name' => '应用运行时通信配置', 'group' => '运维', 'access' => $access('operations.runtime.configure')]);
    Route::post('runtime/reconcile', 'Platform.Administration/reconcileRuntime')->name('operations.reconcileRuntime')
        ->setOption('contract', ['request' => null, 'response' => $response(AdminRuntimeConfigResource::class)])
        ->setOption('meta', ['name' => '运行时对账', 'group' => '运维', 'access' => $access('operations.runtime.configure')]);
    Route::get('deliveries', 'Platform.Administration/deliveries')->name('operations.deliveries')
        ->setOption('contract', ['request' => $request('pagination'), 'response' => $response(AdminDeliveryResource::class, 'paginated')])
        ->setOption('meta', ['name' => '事件投递', 'group' => '运维', 'access' => $access('communications.read')]);
    Route::get('deliveries/:id', 'Platform.Administration/delivery')->name('operations.delivery')
        ->pattern(['id' => '\\d+'])
        ->setOption('contract', ['request' => null, 'response' => $response(AdminDeliveryResource::class)])
        ->setOption('meta', ['name' => '事件投递详情', 'group' => '运维', 'access' => $access('communications.read')]);
    Route::post('deliveries/:id/retry', 'Platform.Administration/retryDelivery')->name('operations.retryDelivery')
        ->setOption('contract', ['request' => null, 'response' => $response(null, 'void', 204)])
        ->setOption('meta', ['name' => '重试事件投递', 'group' => '运维', 'access' => $access('operations.retry')]);
    Route::get('notifications', 'Platform.Administration/notificationStats')->name('operations.notificationStats')
        ->setOption('contract', ['request' => null, 'response' => $response(AdminNotificationStatsResource::class)])
        ->setOption('meta', ['name' => '通知统计', 'group' => '运维', 'access' => $access('communications.read')]);
    Route::get('upgrades', 'Platform.Administration/upgrades')->name('operations.upgrades')
        ->setOption('contract', ['request' => $request('upgradeList'), 'response' => $response(AdminUpgradePlanResource::class, 'collection')])
        ->setOption('meta', ['name' => '系统升级计划', 'group' => '运维', 'access' => $access('operations.read')]);
    Route::get('upgrades/:planId', 'Platform.Administration/upgrade')->name('operations.upgrade')
        ->pattern(['planId' => '[A-Za-z0-9._-]+'])
        ->setOption('contract', ['request' => null, 'response' => $response(AdminUpgradePlanResource::class)])
        ->setOption('meta', ['name' => '系统升级计划详情', 'group' => '运维', 'access' => $access('operations.read')]);
    Route::post('upgrades', 'Platform.Administration/createUpgrade')->name('operations.createUpgrade')
        ->setOption('contract', ['request' => ['validate' => AdminValidate::class, 'scene' => 'upgradeCreate', 'contentType' => 'multipart/form-data'], 'response' => $response(AdminUpgradePlanResource::class, 'item', 201)])
        ->setOption('meta', ['name' => '创建系统升级计划', 'group' => '运维', 'access' => $access('system.update')]);
    Route::post('upgrades/:planId/advance', 'Platform.Administration/advanceUpgrade')->name('operations.advanceUpgrade')
        ->pattern(['planId' => '[A-Za-z0-9._-]+'])
        ->setOption('contract', ['request' => null, 'response' => $response(AdminUpgradePlanResource::class)])
        ->setOption('meta', ['name' => '推进系统升级计划', 'group' => '运维', 'access' => $access('system.update')]);
    Route::post('upgrades/:planId/approve-contract', 'Platform.Administration/approveUpgradeContract')->name('operations.approveUpgradeContract')
        ->pattern(['planId' => '[A-Za-z0-9._-]+'])
        ->setOption('contract', ['request' => null, 'response' => $response(AdminUpgradePlanResource::class)])
        ->setOption('meta', ['name' => '批准系统升级 Contract', 'group' => '运维', 'access' => $access('system.update.contract')]);
    Route::post('upgrades/:planId/recover', 'Platform.Administration/recoverUpgrade')->name('operations.recoverUpgrade')
        ->pattern(['planId' => '[A-Za-z0-9._-]+'])
        ->setOption('contract', ['request' => null, 'response' => $response(AdminUpgradePlanResource::class)])
        ->setOption('meta', ['name' => '恢复系统 Release', 'group' => '运维', 'access' => $access('system.recover')]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

Route::group('admin', function () use ($access, $response, $request) {
    Route::get('audit-logs', 'Platform.Administration/auditLogs')->name('operations.auditLogs')
        ->setOption('contract', ['request' => $request('pagination'), 'response' => $response(AdminAuditLogResource::class, 'paginated')])
        ->setOption('meta', ['name' => '审计日志', 'group' => '审计', 'access' => $access('audit.read')]);
    Route::get('audit-logs/:id', 'Platform.Administration/auditLog')->name('operations.auditLog')
        ->pattern(['id' => '\\d+'])
        ->setOption('contract', ['request' => null, 'response' => $response(AdminAuditLogResource::class)])
        ->setOption('meta', ['name' => '审计日志详情', 'group' => '审计', 'access' => $access('audit.read')]);
    Route::get('reports', 'Platform.Administration/reports')->name('reports.listManaged')
        ->setOption('contract', ['request' => $request('pagination'), 'response' => $response(AdminReportResource::class, 'paginated')])
        ->setOption('meta', ['name' => '举报列表', 'group' => '治理', 'access' => $access('reports.read')]);
    Route::get('reports/:id', 'Platform.Administration/report')->name('reports.getManaged')
        ->setOption('contract', ['request' => null, 'response' => $response(AdminReportResource::class)])
        ->setOption('meta', ['name' => '举报详情', 'group' => '治理', 'access' => $access('reports.read')]);
    Route::post('reports/:id/transition', 'Platform.Administration/transitionReport')->name('reports.transition')
        ->setOption('contract', ['request' => $request('transition'), 'response' => $response(AdminReportResource::class)])
        ->setOption('meta', ['name' => '处理举报', 'group' => '治理', 'access' => $access('reports.manage')]);
    Route::get('users/:id/security', 'Platform.Administration/userSecurity')->name('users.security')
        ->setOption('contract', ['request' => null, 'response' => $response(AdminSecurityResource::class)])
        ->setOption('meta', ['name' => '用户安全概览', 'group' => '安全', 'access' => $access('users.security.read')]);
    Route::get('users/:id/sessions', 'Platform.Administration/sessions')->name('users.sessions')
        ->setOption('contract', ['request' => null, 'response' => $response(AdminSessionResource::class, 'collection')])
        ->setOption('meta', ['name' => '用户 Session', 'group' => '安全', 'access' => $access('users.security.read')]);
    Route::post('users/:id/sessions/:sessionId/revoke', 'Platform.Administration/revokeSession')->name('users.revokeSession')
        ->setOption('contract', ['request' => null, 'response' => $response(null, 'void', 204)])
        ->setOption('meta', ['name' => '撤销 Session', 'group' => '安全', 'access' => $access('users.sessions.revoke')]);
    Route::post('users/:id/sessions/revoke-all', 'Platform.Administration/revokeSessions')->name('users.revokeSessions')
        ->setOption('contract', ['request' => null, 'response' => $response(null, 'void', 204)])
        ->setOption('meta', ['name' => '撤销全部 Session', 'group' => '安全', 'access' => $access('users.sessions.revoke')]);
    Route::get('recovery', 'Platform.Administration/recovery')->name('users.recovery')
        ->setOption('contract', ['request' => $request('pagination'), 'response' => $response(AdminRecoveryChallengeResource::class, 'paginated')])
        ->setOption('meta', ['name' => '账号恢复记录', 'group' => '安全', 'access' => $access('users.recovery.read')]);
    Route::get('community/insights', 'Platform.Administration/communityInsights')->name('community.insights')
        ->setOption('contract', ['request' => null, 'response' => $response(AdminCommunityInsightsResource::class)])
        ->setOption('meta', ['name' => '社区聚合', 'group' => '内容', 'access' => $access('community.insights.read')]);
    Route::get('files/:id/usage', 'Platform.Administration/fileUsage')->name('files.usage')
        ->setOption('contract', ['request' => null, 'response' => $response(AdminFileUsageResource::class)])
        ->setOption('meta', ['name' => '文件使用关系', 'group' => '文件', 'access' => $access('files.usage.read')]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
