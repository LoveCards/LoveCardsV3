<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\shared\Transport\Validation\BatchOperationInput;

Route::group('admin/roles', function () {
    Route::get('', 'Rbac.Roles/list')
    ->name('roles.list')
    ->setOption('contract', ['request' => ['validate' => 'app\\shared\\Transport\\Validation\\PaginationInput', 'scene' => 'Defult'], 'response' => ['resource' => 'app\\modules\\Platform\\Rbac\\Transport\\Api\\RoleResource', 'shape' => 'paginated', 'status' => 200]])
        ->setOption('meta', ['name' => '角色列表', 'group' => '角色', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'roles.read', 'scope' => 'global']]]]);

    Route::get(':id/grants', 'Rbac.Roles/getRoleGrants')
    ->name('roles.getGrants')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Rbac\\Transport\\Api\\GrantResource', 'shape' => 'collection', 'status' => 200]])
        ->setOption('meta', ['name' => '获取角色能力', 'group' => '角色', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'roles.read', 'scope' => 'global']]]]);

    Route::get(':id', 'Rbac.Roles/get')
    ->name('roles.get')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Rbac\\Transport\\Api\\RoleResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '角色详情', 'group' => '角色', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'roles.read', 'scope' => 'global']]]]);

    Route::post('', 'Rbac.Roles/create')
    ->name('roles.create')
    ->setOption('contract', ['request' => ['validate' => 'app\\modules\\Platform\\Rbac\\Transport\\Api\\Roles', 'scene' => 'create'], 'response' => ['resource' => 'app\\shared\\Transport\\Http\\ApiResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '创建角色', 'group' => '角色', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'roles.create', 'scope' => 'global']]]]);

    Route::post('reseed', 'Rbac.Roles/reseed')
    ->name('roles.reseed')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\shared\\Transport\\Http\\ApiResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '重新 seed 能力', 'group' => '角色', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'roles.assign', 'scope' => 'global']]]]);

    Route::post(':id/grants', 'Rbac.Roles/assignGrants')
    ->name('roles.assignGrants')
    ->setOption('contract', ['request' => ['validate' => 'app\\modules\\Platform\\Rbac\\Transport\\Api\\Roles', 'scene' => 'assignGrants'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '分配能力', 'group' => '角色', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'roles.assign', 'scope' => 'global']]]]);

    Route::patch(':id', 'Rbac.Roles/update')
    ->name('roles.update')
    ->setOption('contract', ['request' => ['validate' => 'app\\modules\\Platform\\Rbac\\Transport\\Api\\Roles', 'scene' => 'update'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '编辑角色', 'group' => '角色', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'roles.update', 'scope' => 'global']]]]);

    Route::delete(':id', 'Rbac.Roles/delete')
    ->name('roles.delete')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '删除角色', 'group' => '角色', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'roles.delete', 'scope' => 'global']]]]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

Route::post('admin/roles/batch', 'Rbac.Roles/batch')
    ->name('roles.batch')
    ->setOption('contract', ['request' => ['validate' => BatchOperationInput::class, 'scene' => 'BatchOperate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
    ->middleware(JwtAuthCheck::class)
    ->middleware(PermissionCheck::class)
    ->setOption('meta', ['name' => '角色批量操作', 'group' => '角色', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'roles.delete', 'scope' => 'global']]]]);
