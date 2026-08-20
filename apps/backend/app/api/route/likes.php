<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\modules\Content\Likes\Transport\Api\Likes as LikesValidate;

Route::get('me/likes', 'Content.Likes/list')
    ->name('likes.list')
    ->setOption('contract', ['request' => ['validate' => LikesValidate::class, 'scene' => 'type'], 'response' => ['resource' => 'app\\modules\\Content\\Likes\\Transport\\Api\\LikeResource', 'shape' => 'collection', 'status' => 200]])
    ->setOption('meta', ['name' => '我的点赞', 'group' => '点赞', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'likes.read', 'scope' => 'global']]]])
    ->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

Route::delete('me/likes/:id', 'Content.Likes/unlike')
    ->name('likes.unlike')
    ->setOption('contract', ['request' => ['validate' => LikesValidate::class, 'scene' => 'type'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
    ->setOption('meta', ['name' => '取消点赞', 'group' => '点赞', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'likes.delete', 'scope' => 'global']]]])
    ->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
