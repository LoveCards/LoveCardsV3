<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;

Route::group('admin/permissions', function () {
    Route::get('', 'Rbac.Permissions/list')
    ->name('permissions.list')
    ->setOption('contract', ['request' => ['validate' => 'app\\shared\\Transport\\Validation\\PaginationInput', 'scene' => 'Defult'], 'response' => ['resource' => 'app\\modules\\Platform\\Rbac\\Transport\\Api\\PermissionResource', 'shape' => 'paginated', 'status' => 200]])
        ->setOption('meta', ['name' => '权限列表', 'group' => '权限', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'permissions.read', 'scope' => 'global']]]]);

    Route::get('all', 'Rbac.Permissions/all')
    ->name('permissions.all')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Rbac\\Transport\\Api\\PermissionResource', 'shape' => 'collection', 'status' => 200]])
        ->setOption('meta', ['name' => '全部权限', 'group' => '权限', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'permissions.read', 'scope' => 'global']]]]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
