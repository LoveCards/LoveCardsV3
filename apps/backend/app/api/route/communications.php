<?php
declare(strict_types=1);

use think\facade\Route;
use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\modules\Operations\Administration\Transport\Api\AdminValidate;
use app\modules\Operations\Administration\Transport\Api\AdminNotificationPoliciesResource;
use app\modules\Operations\Administration\Transport\Api\AdminNotificationPoliciesApplyResource;
use app\modules\Operations\Administration\Transport\Api\AdminSignalPoliciesResource;
use app\shared\Authorization\Scope;

$access = static fn (string $capability): array => ['auth' => 'required', 'allOf' => [
    ['capability' => 'admin.access', 'scope' => Scope::GLOBAL],
    ['capability' => $capability, 'scope' => Scope::GLOBAL],
]];
$response = static fn (?string $resource, string $shape = 'item', int $status = 200): array => compact('resource', 'shape', 'status');
$request = static fn (string $scene): array => ['validate' => AdminValidate::class, 'scene' => $scene];

// 通知策略目录：读取使用 communications.read；写入继续要求 communications.configure。
Route::group('admin/communications', function () use ($access, $response, $request) {
    Route::get('policies', 'Platform.AdminCommunications/policies')->name('communications.policies')
        ->setOption('contract', ['request' => null, 'response' => $response(AdminNotificationPoliciesResource::class)])
        ->setOption('meta', ['name' => '通知策略目录', 'group' => '通信', 'access' => $access('communications.read')])
        ;
    Route::put('policies', 'Platform.AdminCommunications/applyPolicies')->name('communications.applyPolicies')
        ->setOption('contract', ['request' => $request('notificationPolicies'), 'response' => $response(AdminNotificationPoliciesApplyResource::class)])
        ->setOption('meta', ['name' => '启停通知策略', 'group' => '通信', 'access' => $access('communications.configure')]);
    Route::get('signals', 'Platform.AdminCommunications/signals')->name('communications.signals')
        ->setOption('contract', ['request' => null, 'response' => $response(AdminSignalPoliciesResource::class)])
        ->setOption('meta', ['name' => '主动更新目录', 'group' => '通信', 'access' => $access('communications.read')]);
    Route::put('signals', 'Platform.AdminCommunications/applySignals')->name('communications.applySignals')
        ->setOption('contract', ['request' => $request('signalPolicies'), 'response' => $response(AdminSignalPoliciesResource::class)])
        ->setOption('meta', ['name' => '启停主动更新', 'group' => '通信', 'access' => $access('communications.configure')]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
