<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\api\middleware\SessionDebounce;
use app\api\middleware\CaptchaCheck;
use app\modules\Content\Cards\Transport\Api\Cards as CardsValidate;
use app\shared\Transport\Validation\PaginationInput;
use app\shared\Transport\Validation\BatchOperationInput;
use app\modules\Content\Cards\Transport\Api\CardResource;
use app\modules\Content\Cards\Transport\Api\CardCreatedResource;
use app\modules\Content\Likes\Transport\Api\LikeCountResource;
use app\modules\Content\Comments\Transport\Api\Comments as CommentsValidate;
use app\modules\Content\Comments\Transport\Api\CommentResource;
use app\modules\Content\Comments\Transport\Api\CommentCreatedResource;

// ─── 公开路由 ───
Route::get('cards/hot', 'Content.Cards/hotList')
    ->name('cards.hot')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => CardResource::class, 'shape' => 'collection', 'status' => 200]])
    ->setOption('meta', ['name' => '热门卡片', 'group' => '卡片', 'public' => true]);

Route::get('cards/search', 'Content.Cards/search')
    ->name('cards.search')
    ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => CardResource::class, 'shape' => 'paginated', 'status' => 200]])
    ->setOption('meta', ['name' => '搜索卡片', 'group' => '卡片', 'public' => true]);

Route::get('cards/:id/comments', 'Content.Comments/cardList')
    ->name('comments.cardList')
    ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => CommentResource::class, 'shape' => 'paginated', 'status' => 200]])
    ->setOption('meta', ['name' => '卡片评论列表', 'group' => '评论', 'public' => true]);

Route::get('cards/:id', 'Content.Cards/get')
    ->name('cards.get')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => CardResource::class, 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '卡片详情', 'group' => '卡片', 'public' => true]);

Route::get('cards', 'Content.Cards/list')
    ->name('cards.list')
    ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => CardResource::class, 'shape' => 'paginated', 'status' => 200]])
    ->setOption('meta', ['name' => '卡片列表', 'group' => '卡片', 'public' => true]);

// ─── 需要能力的路由（合并 all/ 前缀） ───
Route::group('cards', function () {
    Route::post('', 'Content.Cards/create')
        ->name('cards.create')
        ->setOption('contract', ['request' => ['validate' => CardsValidate::class, 'scene' => 'create'], 'response' => ['resource' => CardCreatedResource::class, 'shape' => 'item', 'status' => 201]])
        ->setOption('meta', ['name' => '创建卡片', 'group' => '卡片', 'caps' => ['cards.create']]);

    Route::patch(':id', 'Content.Cards/update')
        ->name('cards.update')
        ->setOption('contract', ['request' => ['validate' => CardsValidate::class, 'scene' => 'allUpdate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '编辑卡片', 'group' => '卡片', 'caps' => ['cards.update', 'cards.update.all']]);

    Route::delete(':id', 'Content.Cards/delete')
        ->name('cards.delete')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '删除卡片', 'group' => '卡片', 'caps' => ['cards.delete', 'cards.delete.all']]);

    Route::post(':id/like', 'Content.Cards/like')
        ->name('cards.like')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => LikeCountResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '点赞卡片', 'group' => '卡片', 'caps' => ['likes.create']]);

    Route::post(':id/comments', 'Content.Comments/create')
        ->name('comments.create')
        ->setOption('contract', ['request' => ['validate' => CommentsValidate::class, 'scene' => 'create'], 'response' => ['resource' => CommentCreatedResource::class, 'shape' => 'item', 'status' => 201]])
        ->setOption('meta', ['name' => '创建评论', 'group' => '评论', 'caps' => ['comments.create']]);
})->middleware(JwtAuthCheck::class)->middleware(CaptchaCheck::class, ['type' => 'captcha'])->middleware(PermissionCheck::class);

// ─── batch 路由（只检查 token，能力在 Service 层检查） ───
Route::post('cards/batch', 'Content.Cards/batch')
    ->name('cards.batch')
    ->setOption('contract', ['request' => ['validate' => BatchOperationInput::class, 'scene' => 'BatchOperate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
    ->middleware(JwtAuthCheck::class)
    ->setOption('meta', ['name' => '卡片批量操作', 'group' => '卡片']);

// ─── users/me 路由（只需 token） ───
Route::get('users/me/cards', 'Content.Cards/listOwn')
    ->name('cards.listOwn')
    ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'Defult'], 'response' => ['resource' => CardResource::class, 'shape' => 'paginated', 'status' => 200]])
    ->middleware(JwtAuthCheck::class)
    ->setOption('meta', ['name' => '我的卡片', 'group' => '卡片']);
