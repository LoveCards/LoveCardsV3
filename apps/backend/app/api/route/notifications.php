<?php
declare(strict_types=1);

use think\facade\Route;
use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\modules\Platform\Notifications\Transport\Api\AdminNotificationQueryValidate;
use app\modules\Platform\Notifications\Transport\Api\AdminNotificationResource;
use app\shared\Authorization\Scope;

$notificationAccess = [
    'auth' => 'required',
    'allOf' => [
        ['capability' => 'admin.access', 'scope' => Scope::GLOBAL],
        ['capability' => 'notifications.read', 'scope' => Scope::GLOBAL],
    ],
];

Route::group('admin/notifications', function () use ($notificationAccess) {
    Route::get('', 'Platform.Notifications/index')->name('notifications.listManaged')
        ->setOption('contract', [
            'request' => ['validate' => AdminNotificationQueryValidate::class, 'scene' => 'list'],
            'response' => ['resource' => AdminNotificationResource::class, 'shape' => 'paginated', 'status' => 200],
        ])
        ->setOption('meta', ['name' => '站内通知', 'group' => '站内通知', 'access' => $notificationAccess]);
    Route::get(':id', 'Platform.Notifications/get')->name('notifications.getManaged')
        ->pattern(['id' => '\\d+'])
        ->setOption('contract', [
            'request' => null,
            'response' => ['resource' => AdminNotificationResource::class, 'shape' => 'item', 'status' => 200],
        ])
        ->setOption('meta', ['name' => '站内通知详情', 'group' => '站内通知', 'access' => $notificationAccess]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
