<?php
declare(strict_types=1);

use think\facade\Route;
use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\modules\Platform\Notifications\Transport\Api\SystemNoticeBatchValidate;
use app\modules\Platform\Notifications\Transport\Api\SystemNoticeBatchResource;
use app\modules\Platform\Notifications\Transport\Api\SystemNoticeLatestBatchResource;
use app\modules\Platform\Notifications\Transport\Api\SystemNoticeRecipientResource;
use app\shared\Authorization\Scope;

$publishAccess = ['auth' => 'required', 'allOf' => [
    ['capability' => 'admin.access', 'scope' => Scope::GLOBAL],
    ['capability' => 'notifications.publish', 'scope' => Scope::GLOBAL],
]];
$request = static fn(string $scene): array => ['validate' => SystemNoticeBatchValidate::class, 'scene' => $scene];
$response = static fn(string $resource, string $shape = 'item', int $status = 200): array => compact('resource', 'shape', 'status');

Route::group('admin/notifications', function () use ($publishAccess, $request, $response) {
    Route::get('recipients', 'Platform.Notifications/recipientCandidates')->name('notifications.recipientCandidates')
        ->setOption('contract', ['request' => $request('candidates'), 'response' => $response(SystemNoticeRecipientResource::class, 'collection')])
        ->setOption('meta', ['name' => '系统通知收件人搜索', 'group' => '站内通知', 'access' => $publishAccess]);
    Route::get('batches/latest', 'Platform.Notifications/latestBatch')->name('notifications.latestBatch')
        ->setOption('contract', ['request' => $request('latest'), 'response' => $response(SystemNoticeLatestBatchResource::class)])
        ->setOption('meta', ['name' => '我的最近系统通知批次', 'group' => '站内通知', 'access' => $publishAccess]);
    Route::post('batches', 'Platform.Notifications/prepareBatch')->name('notifications.prepareBatch')
        ->setOption('contract', ['request' => $request('prepare'), 'response' => $response(SystemNoticeBatchResource::class, 'item', 201)])
        ->setOption('meta', ['name' => '准备系统通知预览', 'group' => '站内通知', 'access' => $publishAccess]);
    Route::get('batches/:id', 'Platform.Notifications/getBatch')->name('notifications.getBatch')
        ->pattern(['id' => '\d+'])
        ->setOption('contract', ['request' => null, 'response' => $response(SystemNoticeBatchResource::class)])
        ->setOption('meta', ['name' => '我的系统通知批次回执', 'group' => '站内通知', 'access' => $publishAccess]);
    Route::post('batches/:id/confirm', 'Platform.Notifications/confirmBatch')->name('notifications.confirmBatch')
        ->pattern(['id' => '\d+'])
        ->setOption('contract', ['request' => $request('confirm'), 'response' => $response(SystemNoticeBatchResource::class, 'item', 202)])
        ->setOption('meta', ['name' => '确认发布系统通知', 'group' => '站内通知', 'access' => $publishAccess]);
    Route::post('batches/:id/retry', 'Platform.Notifications/retryBatch')->name('notifications.retryBatch')
        ->pattern(['id' => '\d+'])
        ->setOption('contract', ['request' => $request('retry'), 'response' => $response(SystemNoticeBatchResource::class, 'item', 202)])
        ->setOption('meta', ['name' => '重试系统通知失败任务', 'group' => '站内通知', 'access' => $publishAccess]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
