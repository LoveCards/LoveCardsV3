<?php

use think\facade\Route;
use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\modules\Platform\Files\Transport\Api\Files as FilesValidate;
use app\modules\Platform\Files\Transport\Api\FileResource;
use app\modules\Platform\Files\Transport\Api\DirectUploadResource;
use app\shared\Authorization\Scope;
use app\shared\Transport\Http\ApiResource;
use app\shared\Transport\Validation\PaginationInput;

$required = static fn (array $allOf = [], array $anyOf = []): array => [
    'auth' => 'required', 'allOf' => $allOf, 'anyOf' => $anyOf,
];
$grant = static fn (string $capability, string $scope): array => compact('capability', 'scope');

Route::group('me/files', function () use ($required, $grant) {
    Route::post('', 'Storage.Upload/upload')->name('files.upload')
        ->setOption('contract', ['request' => ['validate' => FilesValidate::class, 'scene' => 'upload', 'contentType' => 'multipart/form-data'], 'response' => ['resource' => FileResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '上传文件', 'group' => '文件', 'access' => $required([$grant('files.upload', Scope::GLOBAL)])]);
    Route::get('', 'Storage.Upload/listOwn')->name('files.listOwn')
        ->setOption('contract', ['request' => ['validate' => FilesValidate::class, 'scene' => 'listOwn'], 'response' => ['resource' => FileResource::class, 'shape' => 'paginated', 'status' => 200]])
        ->setOption('meta', ['name' => '我的文件', 'group' => '文件', 'access' => $required([$grant('files.read', Scope::OWN)])]);
    Route::post('direct', 'Storage.Upload/direct')->name('files.direct')
        ->setOption('contract', ['request' => ['validate' => FilesValidate::class, 'scene' => 'direct'], 'response' => ['resource' => DirectUploadResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '直传凭证', 'group' => '文件', 'access' => $required([$grant('files.upload', Scope::GLOBAL)])]);
    Route::patch(':id/confirm', 'Storage.Upload/confirm')->name('files.confirm')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '确认直传', 'group' => '文件', 'access' => $required([$grant('files.upload', Scope::GLOBAL)])]);
    Route::get(':id', 'Storage.Upload/get')->name('files.get')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => FileResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '我的文件详情', 'group' => '文件', 'access' => $required([], [$grant('files.read', Scope::OWN), $grant('files.read', Scope::ALL)])]);
    Route::delete(':id', 'Storage.Upload/delete')->name('files.delete')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '删除我的文件', 'group' => '文件', 'access' => $required([], [$grant('files.delete', Scope::OWN), $grant('files.delete', Scope::ALL)])]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

Route::group('admin/files', function () use ($required, $grant) {
    $admin = $grant('admin.access', Scope::GLOBAL);
    Route::get('', 'Storage.Upload/list')->name('files.listManaged')
        ->setOption('contract', ['request' => ['validate' => FilesValidate::class, 'scene' => 'list'], 'response' => ['resource' => FileResource::class, 'shape' => 'paginated', 'status' => 200]])
        ->setOption('meta', ['name' => '管理文件列表', 'group' => '文件', 'access' => $required([$admin, $grant('files.read', Scope::ALL)])]);
    Route::get(':id', 'Storage.Upload/get')->name('files.getManaged')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => FileResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '管理文件详情', 'group' => '文件', 'access' => $required([$admin, $grant('files.read', Scope::ALL)])]);
    Route::delete(':id', 'Storage.Upload/deleteManaged')->name('files.deleteManaged')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '管理文件删除', 'group' => '文件', 'access' => $required([$admin, $grant('files.delete', Scope::ALL)])]);
    Route::delete(':id/purge', 'Storage.Upload/purge')->name('files.purge')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '永久删除文件', 'group' => '文件', 'access' => $required([$admin, $grant('files.purge', Scope::ALL)])]);
    Route::delete('expired', 'Storage.Upload/cleanup')->name('files.cleanup')
        ->setOption('contract', ['request' => ['validate' => FilesValidate::class, 'scene' => 'cleanup'], 'response' => ['resource' => ApiResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '清理过期文件', 'group' => '文件', 'access' => $required([$admin, $grant('files.cleanup', Scope::GLOBAL)])]);
    Route::post('batch', 'Storage.Upload/batch')->name('files.batchManaged')
        ->setOption('contract', ['request' => ['validate' => FilesValidate::class, 'scene' => 'batch'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '管理文件批量操作', 'group' => '文件', 'access' => $required([$admin])]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
