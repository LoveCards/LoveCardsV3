<?php
declare(strict_types=1);

use think\facade\Route;
use app\api\middleware\InternalTokenCheck;
use app\modules\Platform\Notifications\Transport\Api\InternalRealtimeValidate;
use app\modules\Platform\Notifications\Transport\Api\InternalTicketVerifyResource;
use app\modules\Platform\Notifications\Transport\Api\InternalNotificationsPollResource;

$response = static fn (?string $resource, string $shape = 'item', int $status = 200): array => compact('resource', 'shape', 'status');

// Gateway 内部通道：共享密钥 Bearer 校验（InternalTokenCheck），非 JWT、非 capability。
Route::group('internal/realtime', function () use ($response) {
    Route::post('ticket/verify', 'Platform.RealtimeInternal/verifyTicket')->name('internal.realtimeTicketVerify')
        ->setOption('contract', ['request' => ['validate' => InternalRealtimeValidate::class, 'scene' => 'verify'], 'response' => $response(InternalTicketVerifyResource::class)])
        ->setOption('meta', ['name' => '流凭证校验（内部）', 'group' => '运行时', 'access' => ['auth' => 'public']]);
    Route::get('notifications-since', 'Platform.RealtimeInternal/notificationsSince')->name('internal.realtimeNotificationsSince')
        ->setOption('contract', ['request' => ['validate' => InternalRealtimeValidate::class, 'scene' => 'since'], 'response' => $response(InternalNotificationsPollResource::class)])
        ->setOption('meta', ['name' => '通知游标回源（内部）', 'group' => '运行时', 'access' => ['auth' => 'public']]);
})->middleware(InternalTokenCheck::class);
