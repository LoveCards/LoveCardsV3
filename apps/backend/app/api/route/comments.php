<?php

use think\facade\Route;
use app\api\middleware\JwtAuthCheck;
use app\api\middleware\OptionalJwtAuth;
use app\api\middleware\PermissionCheck;
use app\modules\Content\Comments\Transport\Api\Comments as CommentsValidate;
use app\modules\Content\Comments\Transport\Api\CommentResource;
use app\modules\Content\Likes\Transport\Api\LikeCountResource;
use app\shared\Authorization\Scope;
use app\shared\Transport\Validation\PaginationInput;
use app\shared\Transport\Validation\BatchOperationInput;

$required = static fn (array $allOf = [], array $anyOf = []): array => [
    'auth' => 'required', 'allOf' => $allOf, 'anyOf' => $anyOf,
];
$grant = static fn (string $capability, string $scope): array => compact('capability', 'scope');

Route::get('comments/:id', 'Content.Comments/get')->name('comments.get')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => CommentResource::class, 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '评论详情', 'group' => '评论', 'access' => ['auth' => 'public']])
    ->middleware(OptionalJwtAuth::class);

Route::post('comments/:id/like', 'Content.Comments/like')->name('comments.like')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => LikeCountResource::class, 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '点赞评论', 'group' => '评论', 'access' => $required([$grant('likes.create', Scope::GLOBAL)])])
    ->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

Route::delete('comments/:id/like', 'Content.Comments/unlike')->name('comments.unlike')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
    ->setOption('meta', ['name' => '取消点赞评论', 'group' => '评论', 'access' => $required([$grant('likes.delete', Scope::GLOBAL)])])
    ->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

Route::group('me/comments', function () use ($required, $grant) {
    Route::get('', 'Content.Comments/listOwn')->name('comments.listOwn')
        ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => CommentResource::class, 'shape' => 'paginated', 'status' => 200]])
        ->setOption('meta', ['name' => '我的评论', 'group' => '评论', 'access' => $required([$grant('comments.read', Scope::OWN)])]);
    Route::delete(':id', 'Content.Comments/delete')->name('comments.delete')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '删除我的评论', 'group' => '评论', 'access' => $required([], [$grant('comments.delete', Scope::OWN), $grant('comments.delete', Scope::ALL)])]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

Route::group('admin/comments', function () use ($required, $grant) {
    $admin = $grant('admin.access', Scope::GLOBAL);
    Route::get('', 'Content.Comments/list')->name('comments.listManaged')
        ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => CommentResource::class, 'shape' => 'paginated', 'status' => 200]])
        ->setOption('meta', ['name' => '管理评论列表', 'group' => '评论', 'access' => $required([$admin, $grant('comments.read', Scope::ALL)])]);
    Route::get(':id', 'Content.Comments/getManaged')->name('comments.getManaged')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => CommentResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '管理评论详情', 'group' => '评论', 'access' => $required([$admin, $grant('comments.read', Scope::ALL)])]);
    Route::patch(':id', 'Content.Comments/moderate')->name('comments.moderateManaged')
        ->setOption('contract', ['request' => ['validate' => CommentsValidate::class, 'scene' => 'moderate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '管理评论编辑与审核', 'group' => '评论', 'access' => $required([$admin], [$grant('comments.update', Scope::ALL), $grant('comments.moderate', Scope::ALL)])]);
    Route::delete(':id', 'Content.Comments/deleteManaged')->name('comments.deleteManaged')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '管理评论删除', 'group' => '评论', 'access' => $required([$admin, $grant('comments.delete', Scope::ALL)])]);
    Route::post('batch', 'Content.Comments/batch')->name('comments.batchManaged')
        ->setOption('contract', ['request' => ['validate' => BatchOperationInput::class, 'scene' => 'AdminBatchOperate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '管理评论批量操作', 'group' => '评论', 'access' => $required([$admin])]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

Route::group('admin/cards', function () use ($required, $grant) {
    $access = $required([
        $grant('admin.access', Scope::GLOBAL),
        $grant('comments.pin', Scope::ALL),
    ]);
    Route::put(':id/pinned-comment', 'Content.Comments/pin')->name('comments.pinManaged')
        ->setOption('contract', ['request' => ['validate' => CommentsValidate::class, 'scene' => 'pin'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '置顶卡片评论', 'group' => '评论', 'access' => $access]);
    Route::delete(':id/pinned-comment', 'Content.Comments/unpin')->name('comments.unpinManaged')
        ->setOption('contract', ['request' => ['validate' => CommentsValidate::class, 'scene' => 'unpin'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '取消置顶卡片评论', 'group' => '评论', 'access' => $access]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
