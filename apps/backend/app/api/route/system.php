<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;

Route::group('admin/system', function () {
    Route::get('update', 'System.System/update')
    ->name('system.update')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Operations\\Version\\Transport\\Api\\SystemResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '系统更新', 'group' => '系统', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'system.update', 'scope' => 'global']]]]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
