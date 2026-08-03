<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\modules\Content\Comments\Transport\Api\Comments as CommentsValidate;
use app\modules\Content\Comments\Transport\Api\CommentResource;
use app\modules\Content\Comments\Transport\Api\CommentCreatedResource;
use app\shared\Transport\Validation\PaginationInput;
use app\shared\Transport\Validation\BatchOperationInput;

// ─── 公开路由 ───
Route::get('comments/:id', 'Content.Comments/get')
    ->name('comments.get')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => CommentResource::class, 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '评论详情', 'group' => '评论', 'public' => true]);

// ─── 需要能力的路由（合并 all/ 前缀） ───
Route::group('comments', function () {
    Route::patch(':id', 'Content.Comments/update')
        ->name('comments.update')
        ->setOption('contract', ['request' => ['validate' => CommentsValidate::class, 'scene' => 'allUpdate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '编辑评论', 'group' => '评论', 'caps' => ['comments.update', 'comments.update.all']]);

    Route::delete(':id', 'Content.Comments/delete')
        ->name('comments.delete')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '删除评论', 'group' => '评论', 'caps' => ['comments.delete', 'comments.delete.all']]);

    Route::get('', 'Content.Comments/list')
        ->name('comments.list')
        ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => CommentResource::class, 'shape' => 'paginated', 'status' => 200]])
        ->setOption('meta', ['name' => '评论管理列表', 'group' => '评论', 'caps' => ['comments.read', 'comments.read.all']]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

// ─── batch 路由（只检查 token） ───
Route::post('comments/batch', 'Content.Comments/batch')
    ->name('comments.batch')
    ->setOption('contract', ['request' => ['validate' => BatchOperationInput::class, 'scene' => 'BatchOperate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
    ->middleware(JwtAuthCheck::class)
    ->setOption('meta', ['name' => '评论批量操作', 'group' => '评论']);

// ─── users/me 路由（只需 token） ───
Route::get('users/me/comments', 'Content.Comments/listOwn')
    ->name('comments.listOwn')
    ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => CommentResource::class, 'shape' => 'paginated', 'status' => 200]])
    ->middleware(JwtAuthCheck::class)
    ->setOption('meta', ['name' => '我的评论', 'group' => '评论']);
