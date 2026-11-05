<?php

use think\facade\Route;
use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\modules\Content\Tags\Transport\Api\Tags as TagsValidate;
use app\modules\Content\Tags\Transport\Api\TagResource;
use app\modules\Content\Tags\Transport\Api\TagsPolicyResource;
use app\shared\Authorization\Scope;
use app\shared\Transport\Validation\PaginationInput;
use app\shared\Transport\Validation\BatchOperationInput;

$required = static fn (array $allOf = [], array $anyOf = []): array => [
    'auth' => 'required', 'allOf' => $allOf, 'anyOf' => $anyOf,
];
$grant = static fn (string $capability, string $scope): array => compact('capability', 'scope');

Route::get('tags/policy', 'Content.Tags/policy')->name('tags.policy')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => TagsPolicyResource::class, 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '标签创建规则', 'group' => '标签', 'access' => ['auth' => 'public']]);
Route::get('tags/:id', 'Content.Tags/get')->name('tags.get')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => TagResource::class, 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '标签详情', 'group' => '标签', 'access' => ['auth' => 'public']]);
Route::get('tags', 'Content.Tags/list')->name('tags.list')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => TagResource::class, 'shape' => 'collection', 'status' => 200]])
    ->setOption('meta', ['name' => '标签列表', 'group' => '标签', 'access' => ['auth' => 'public']]);

Route::group('me/tags', function () use ($required, $grant) {
    Route::get('', 'Content.Tags/listOwn')->name('tags.listOwn')
        ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => TagResource::class, 'shape' => 'paginated', 'status' => 200]])
        ->setOption('meta', ['name' => '我的标签', 'group' => '标签', 'access' => $required([$grant('tags.read', Scope::OWN)])]);
    Route::post('', 'Content.Tags/create')->name('tags.create')
        ->setOption('contract', ['request' => ['validate' => TagsValidate::class, 'scene' => 'selfCreate'], 'response' => ['resource' => TagResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '创建标签', 'group' => '标签', 'access' => $required([$grant('tags.create', Scope::GLOBAL)])]);
    Route::patch(':id', 'Content.Tags/update')->name('tags.update')
        ->setOption('contract', ['request' => ['validate' => TagsValidate::class, 'scene' => 'selfUpdate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '编辑我的标签', 'group' => '标签', 'access' => $required([], [$grant('tags.update', Scope::OWN), $grant('tags.update', Scope::ALL)])]);
    Route::delete(':id', 'Content.Tags/delete')->name('tags.delete')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '删除我的标签', 'group' => '标签', 'access' => $required([], [$grant('tags.delete', Scope::OWN), $grant('tags.delete', Scope::ALL)])]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

Route::group('admin/tags', function () use ($required, $grant) {
    $admin = $grant('admin.access', Scope::GLOBAL);
    Route::get('', 'Content.Tags/listAll')->name('tags.listManaged')
        ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => TagResource::class, 'shape' => 'paginated', 'status' => 200]])
        ->setOption('meta', ['name' => '管理标签列表', 'group' => '标签', 'access' => $required([$admin, $grant('tags.read', Scope::ALL)])]);
    Route::get(':id', 'Content.Tags/getManaged')->name('tags.getManaged')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => TagResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '管理标签详情', 'group' => '标签', 'access' => $required([$admin, $grant('tags.read', Scope::ALL)])]);
    Route::patch(':id', 'Content.Tags/updateManaged')->name('tags.updateManaged')
        ->setOption('contract', ['request' => ['validate' => TagsValidate::class, 'scene' => 'adminUpdate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '管理标签编辑', 'group' => '标签', 'access' => $required([$admin, $grant('tags.update', Scope::ALL)])]);
    Route::delete(':id', 'Content.Tags/deleteManaged')->name('tags.deleteManaged')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '管理标签删除', 'group' => '标签', 'access' => $required([$admin, $grant('tags.delete', Scope::ALL)])]);
    Route::post('batch', 'Content.Tags/batch')->name('tags.batchManaged')
        ->setOption('contract', ['request' => ['validate' => BatchOperationInput::class, 'scene' => 'AdminBatchOperate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '管理标签批量操作', 'group' => '标签', 'access' => $required([$admin])]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
