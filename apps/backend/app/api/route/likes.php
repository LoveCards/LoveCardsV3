<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;

Route::get('likes', 'Content.Likes/list')
    ->name('likes.list')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Content\\Likes\\Transport\\Api\\LikeResource', 'shape' => 'collection', 'status' => 200]])
    ->setOption('meta', ['name' => '我的点赞', 'group' => '点赞', 'caps' => ['likes.read']])
    ->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

Route::delete('likes/:id', 'Content.Likes/unlike')
    ->name('likes.unlike')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
    ->setOption('meta', ['name' => '取消点赞', 'group' => '点赞', 'caps' => ['likes.delete']])
    ->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
