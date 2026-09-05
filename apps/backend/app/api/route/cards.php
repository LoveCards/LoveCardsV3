<?php

use think\facade\Route;
use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\api\middleware\CaptchaCheck;
use app\api\middleware\OptionalJwtAuth;
use app\modules\Content\Cards\Transport\Api\Cards as CardsValidate;
use app\modules\Content\Cards\Transport\Api\PublicUserCards as PublicUserCardsValidate;
use app\modules\Content\Cards\Transport\Api\FollowingFeed as FollowingFeedValidate;
use app\modules\Content\Cards\Transport\Api\CardResource;
use app\modules\Content\Cards\Transport\Api\CardCreatedResource;
use app\modules\Content\Comments\Transport\Api\Comments as CommentsValidate;
use app\modules\Content\Comments\Transport\Api\CommentResource;
use app\modules\Content\Comments\Transport\Api\CommentCreatedResource;
use app\modules\Content\Likes\Transport\Api\LikeCountResource;
use app\shared\Authorization\Scope;
use app\shared\Transport\Validation\PaginationInput;
use app\shared\Transport\Validation\BatchOperationInput;

$required = static fn (array $allOf = [], array $anyOf = []): array => [
    'auth' => 'required', 'allOf' => $allOf, 'anyOf' => $anyOf,
];
$grant = static fn (string $capability, string $scope): array => compact('capability', 'scope');

Route::get('cards/hot', 'Content.Cards/hotList')
    ->name('cards.hot')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => CardResource::class, 'shape' => 'collection', 'status' => 200]])
    ->setOption('meta', ['name' => '热门卡片', 'group' => '卡片', 'access' => ['auth' => 'public']])
    ->middleware(OptionalJwtAuth::class);

Route::get('cards/search', 'Content.Cards/search')
    ->name('cards.search')
    ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => CardResource::class, 'shape' => 'paginated', 'status' => 200]])
    ->setOption('meta', ['name' => '搜索卡片', 'group' => '卡片', 'access' => ['auth' => 'public']])
    ->middleware(OptionalJwtAuth::class);

Route::get('users/by-handle/:handle/cards', 'Content.Cards/listPublicByUser')
    ->name('cards.publicByHandle')
    ->pattern(['handle' => '[^/]+'])
    ->setOption('contract', ['request' => ['validate' => PublicUserCardsValidate::class, 'scene' => 'list'], 'response' => ['resource' => CardResource::class, 'shape' => 'paginated', 'status' => 200]])
    ->setOption('meta', ['name' => '按用户读取公开卡片', 'group' => '卡片', 'access' => ['auth' => 'public']])
    ->middleware(OptionalJwtAuth::class);
Route::get('users/:uid/cards', 'Content.Cards/listPublicByUid')
    ->name('cards.listPublicByUser')
    ->setOption('contract', ['request' => ['validate' => PublicUserCardsValidate::class, 'scene' => 'list'], 'response' => ['resource' => CardResource::class, 'shape' => 'paginated', 'status' => 200]])
    ->setOption('meta', ['name' => '按 UID 读取公开卡片', 'group' => '卡片', 'access' => ['auth' => 'public']])
    ->middleware(OptionalJwtAuth::class);

Route::get('cards/:id/comments', 'Content.Comments/cardList')
    ->name('comments.cardList')
    ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => CommentResource::class, 'shape' => 'paginated', 'status' => 200]])
    ->setOption('meta', ['name' => '卡片评论列表', 'group' => '评论', 'access' => ['auth' => 'public']])
    ->middleware(OptionalJwtAuth::class);

Route::get('cards/:id', 'Content.Cards/get')
    ->name('cards.get')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => CardResource::class, 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '卡片详情', 'group' => '卡片', 'access' => ['auth' => 'public']])
    ->middleware(OptionalJwtAuth::class);

Route::get('cards', 'Content.Cards/list')
    ->name('cards.list')
    ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => CardResource::class, 'shape' => 'paginated', 'status' => 200]])
    ->setOption('meta', ['name' => '卡片列表', 'group' => '卡片', 'access' => ['auth' => 'public']])
    ->middleware(OptionalJwtAuth::class);

Route::group('me/cards', function () use ($required, $grant) {
    Route::get('', 'Content.Cards/listOwn')->name('cards.listOwn')
        ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => CardResource::class, 'shape' => 'paginated', 'status' => 200]])
        ->setOption('meta', ['name' => '我的卡片', 'group' => '卡片', 'access' => $required([$grant('cards.read', Scope::OWN)])]);
    Route::post('', 'Content.Cards/create')->name('cards.create')
        ->setOption('contract', ['request' => ['validate' => CardsValidate::class, 'scene' => 'selfCreate'], 'response' => ['resource' => CardCreatedResource::class, 'shape' => 'item', 'status' => 201]])
        ->setOption('meta', ['name' => '创建卡片', 'group' => '卡片', 'access' => $required([$grant('cards.create', Scope::GLOBAL)])]);
    Route::patch(':id', 'Content.Cards/update')->name('cards.update')
        ->setOption('contract', ['request' => ['validate' => CardsValidate::class, 'scene' => 'selfUpdate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '编辑我的卡片', 'group' => '卡片', 'access' => $required([], [$grant('cards.update', Scope::OWN), $grant('cards.update', Scope::ALL)])]);
    Route::put(':id/pinned-comment', 'Content.Comments/pinOwn')->name('comments.pin')
        ->setOption('contract', ['request' => ['validate' => CommentsValidate::class, 'scene' => 'pinOwn'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '置顶我的卡片评论', 'group' => '评论', 'access' => $required([$grant('comments.pin', Scope::OWN)])]);
    Route::delete(':id/pinned-comment', 'Content.Comments/unpinOwn')->name('comments.unpin')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '取消置顶我的卡片评论', 'group' => '评论', 'access' => $required([$grant('comments.pin', Scope::OWN)])]);
    Route::delete(':id', 'Content.Cards/delete')->name('cards.delete')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '删除我的卡片', 'group' => '卡片', 'access' => $required([], [$grant('cards.delete', Scope::OWN), $grant('cards.delete', Scope::ALL)])]);
    Route::get(':id', 'Content.Cards/getOwn')->name('cards.getOwn')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => CardResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '我的卡片详情', 'group' => '卡片', 'access' => $required([$grant('cards.read', Scope::OWN)])]);
})->middleware(JwtAuthCheck::class)->middleware(CaptchaCheck::class, ['type' => 'captcha'])->middleware(PermissionCheck::class);

Route::get('me/feed/following', 'Content.Cards/listFollowing')
    ->name('cards.listFollowing')
    ->setOption('contract', ['request' => ['validate' => FollowingFeedValidate::class, 'scene' => 'list'], 'response' => ['resource' => CardResource::class, 'shape' => 'paginated', 'status' => 200]])
    ->setOption('meta', ['name' => '关注卡片流', 'group' => '卡片', 'access' => ['auth' => 'required']])
    ->middleware(JwtAuthCheck::class);

Route::group('admin/cards', function () use ($required, $grant) {
    $admin = $grant('admin.access', Scope::GLOBAL);
    Route::get('', 'Content.Cards/listAll')->name('cards.listManaged')
        ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => CardResource::class, 'shape' => 'paginated', 'status' => 200]])
        ->setOption('meta', ['name' => '管理卡片列表', 'group' => '卡片', 'access' => $required([$admin, $grant('cards.read', Scope::ALL)])]);
    Route::get(':id', 'Content.Cards/getManaged')->name('cards.getManaged')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => CardResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '管理卡片详情', 'group' => '卡片', 'access' => $required([$admin, $grant('cards.read', Scope::ALL)])]);
    Route::patch(':id', 'Content.Cards/updateManaged')->name('cards.updateManaged')
        ->setOption('contract', ['request' => ['validate' => CardsValidate::class, 'scene' => 'adminUpdate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '管理卡片编辑', 'group' => '卡片', 'access' => $required([$admin, $grant('cards.update', Scope::ALL)])]);
    Route::delete(':id', 'Content.Cards/deleteManaged')->name('cards.deleteManaged')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '管理卡片删除', 'group' => '卡片', 'access' => $required([$admin, $grant('cards.delete', Scope::ALL)])]);
    Route::post('batch', 'Content.Cards/batch')->name('cards.batchManaged')
        ->setOption('contract', ['request' => ['validate' => BatchOperationInput::class, 'scene' => 'AdminBatchOperate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '管理卡片批量操作', 'group' => '卡片', 'access' => $required([$admin])]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

Route::post('cards/:id/like', 'Content.Cards/like')->name('cards.like')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => LikeCountResource::class, 'shape' => 'item', 'status' => 200]])
    ->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class)
    ->setOption('meta', ['name' => '点赞卡片', 'group' => '卡片', 'access' => $required([$grant('likes.create', Scope::GLOBAL)])]);

Route::delete('cards/:id/like', 'Content.Cards/unlike')->name('cards.unlike')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
    ->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class)
    ->setOption('meta', ['name' => '取消点赞卡片', 'group' => '卡片', 'access' => $required([$grant('likes.delete', Scope::GLOBAL)])]);

Route::post('cards/:id/comments', 'Content.Comments/create')->name('comments.create')
    ->setOption('contract', ['request' => ['validate' => CommentsValidate::class, 'scene' => 'selfCreate'], 'response' => ['resource' => CommentCreatedResource::class, 'shape' => 'item', 'status' => 201]])
    ->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class)
    ->setOption('meta', ['name' => '创建评论', 'group' => '评论', 'access' => $required([$grant('comments.create', Scope::GLOBAL)])]);
