<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;

Route::group('files', function () {
    Route::post('', 'Storage.Upload/upload')
    ->name('files.upload')
    ->setOption('contract', ['request' => ['validate' => 'app\\modules\\Platform\\Files\\Transport\\Api\\Files', 'scene' => 'upload'], 'response' => ['resource' => 'app\\modules\\Platform\\Files\\Transport\\Api\\FileResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '上传文件', 'group' => '文件', 'caps' => ['files.upload']]);

    Route::get('', 'Storage.Upload/list')
    ->name('files.list')
    ->setOption('contract', ['request' => ['validate' => 'app\\shared\\Transport\\Validation\\PaginationInput', 'scene' => 'Defult'], 'response' => ['resource' => 'app\\modules\\Platform\\Files\\Transport\\Api\\FileResource', 'shape' => 'paginated', 'status' => 200]])
        ->setOption('meta', ['name' => '文件列表', 'group' => '文件', 'caps' => ['files.read', 'files.read.all']]);

    Route::post('direct', 'Storage.Upload/direct')
    ->name('files.direct')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Files\\Transport\\Api\\DirectUploadResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '直传凭证', 'group' => '文件', 'caps' => ['files.upload']]);

    Route::delete('expired', 'Storage.Upload/cleanup')
    ->name('files.cleanup')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\shared\\Transport\\Http\\ApiResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '清理过期文件', 'group' => '文件', 'caps' => ['files.delete.all']]);

    Route::patch(':id/confirm', 'Storage.Upload/confirm')
    ->name('files.confirm')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '确认直传', 'group' => '文件', 'caps' => ['files.upload']]);

    Route::get(':id', 'Storage.Upload/get')
    ->name('files.get')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Files\\Transport\\Api\\FileResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '文件详情', 'group' => '文件', 'caps' => ['files.read', 'files.read.all']]);

    Route::delete(':id', 'Storage.Upload/allDelete')
    ->name('files.allDelete')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '删除文件', 'group' => '文件', 'caps' => ['files.delete', 'files.delete.all']]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

// batch 路由（检查 token + 能力，Service 层做 owner 验证）
Route::post('files/batch', 'Storage.Upload/batch')
    ->name('files.batch')
    ->setOption('contract', ['request' => ['validate' => 'app\\modules\\Platform\\Files\\Transport\\Api\\Files', 'scene' => 'batch'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
    ->middleware(JwtAuthCheck::class)
    ->middleware(PermissionCheck::class)
    ->setOption('meta', ['name' => '文件批量操作', 'group' => '文件', 'caps' => ['files.update', 'files.update.all', 'files.delete', 'files.delete.all']]);

// users/me/files 路由（严格本人文件列表）
Route::get('users/me/files', 'Storage.Upload/listOwn')
    ->name('files.listOwn')
    ->setOption('contract', ['request' => ['validate' => 'app\\shared\\Transport\\Validation\\PaginationInput', 'scene' => 'Defult'], 'response' => ['resource' => 'app\\modules\\Platform\\Files\\Transport\\Api\\FileResource', 'shape' => 'paginated', 'status' => 200]])
    ->middleware(JwtAuthCheck::class)
    ->setOption('meta', ['name' => '我的文件', 'group' => '文件']);
