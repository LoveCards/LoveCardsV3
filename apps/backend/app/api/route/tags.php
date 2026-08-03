<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\modules\Content\Tags\Transport\Api\Tags as TagsValidate;
use app\modules\Content\Tags\Transport\Api\TagResource;
use app\shared\Transport\Validation\PaginationInput;
use app\shared\Transport\Validation\BatchOperationInput;

// ─── 标签分页列表（受保护，必须在 :id 之前注册）───
Route::get('tags/all', 'Content.Tags/listAll')
    ->name('tags.listAll')
    ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => TagResource::class, 'shape' => 'paginated', 'status' => 200]])
    ->middleware(JwtAuthCheck::class)
    ->middleware(PermissionCheck::class)
    ->setOption('meta', ['name' => '标签分页列表', 'group' => '标签', 'caps' => ['tags.read', 'tags.read.all']]);

// ─── 公开路由 ───
Route::get('tags/:id', 'Content.Tags/get')
    ->name('tags.get')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => TagResource::class, 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '标签详情', 'group' => '标签', 'public' => true]);

Route::get('tags', 'Content.Tags/list')
    ->name('tags.list')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => TagResource::class, 'shape' => 'collection', 'status' => 200]])
    ->setOption('meta', ['name' => '标签列表', 'group' => '标签', 'public' => true]);

// ─── 需要能力的路由（合并 all/ 前缀） ───
Route::group('tags', function () {
    Route::post('', 'Content.Tags/create')
        ->name('tags.create')
        ->setOption('contract', ['request' => ['validate' => TagsValidate::class, 'scene' => 'create'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '创建标签', 'group' => '标签', 'caps' => ['tags.create']]);

    Route::patch(':id', 'Content.Tags/update')
        ->name('tags.update')
        ->setOption('contract', ['request' => ['validate' => TagsValidate::class, 'scene' => 'allUpdate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '编辑标签', 'group' => '标签', 'caps' => ['tags.update', 'tags.update.all']]);

    Route::delete(':id', 'Content.Tags/delete')
        ->name('tags.delete')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '删除标签', 'group' => '标签', 'caps' => ['tags.delete', 'tags.delete.all']]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

// ─── batch 路由（只检查 token） ───
Route::post('tags/batch', 'Content.Tags/batch')
    ->name('tags.batch')
    ->setOption('contract', ['request' => ['validate' => BatchOperationInput::class, 'scene' => 'BatchOperate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
    ->middleware(JwtAuthCheck::class)
    ->setOption('meta', ['name' => '标签批量操作', 'group' => '标签']);
