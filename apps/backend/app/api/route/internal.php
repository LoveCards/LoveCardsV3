<?php
declare(strict_types=1);

use think\facade\Route;
use app\api\middleware\InternalTokenCheck;
use app\api\middleware\EventingCronTokenCheck;
use app\modules\Operations\Runtime\Transport\Api\InternalRealtimeValidate;
use app\modules\Operations\Runtime\Transport\Api\InternalTicketVerifyResource;
use app\modules\Platform\Config\Transport\Api\InternalRedisConfigResource;
use app\modules\Platform\Eventing\Transport\Api\ScheduledRunResource;

$response = static fn (?string $resource, string $shape = 'item', int $status = 200): array => compact('resource', 'shape', 'status');

// Gateway 内部通道：共享密钥 Bearer 校验（InternalTokenCheck），非 JWT、非 capability。
Route::group('internal/realtime', function () use ($response) {
    Route::get('redis-config', 'System.Config/internalRedisConfig')->name('internal.realtimeRedisConfig')
        ->setOption('contract', ['request' => null, 'response' => $response(InternalRedisConfigResource::class)])
        ->setOption('meta', ['name' => 'Redis 连接配置（内部）', 'group' => '运行时', 'access' => ['auth' => 'public']]);
    Route::post('ticket/verify', 'Platform.RealtimeInternal/verifyTicket')->name('internal.realtimeTicketVerify')
        ->setOption('contract', ['request' => ['validate' => InternalRealtimeValidate::class, 'scene' => 'verify'], 'response' => $response(InternalTicketVerifyResource::class)])
        ->setOption('meta', ['name' => '流凭证校验（内部）', 'group' => '运行时', 'access' => ['auth' => 'public']]);
})->middleware(InternalTokenCheck::class);

// URL cron 独立凭据；配置为 HTTP 触发源时才允许领取，且共享 CLI 一次性用例。
Route::post('internal/eventing/run-once', 'System.EventingCron/runOnce')->name('internal.eventingRunOnce')
    ->setOption('contract', ['request' => null, 'response' => $response(ScheduledRunResource::class)])
    ->setOption('meta', ['name' => '事件定时消费（内部）', 'group' => '运行时', 'access' => ['auth' => 'public']])
    ->middleware(EventingCronTokenCheck::class);
