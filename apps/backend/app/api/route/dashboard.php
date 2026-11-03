<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;

Route::get('admin/dashboard', 'System.Dashboard/index')
    ->name('dashboard.index')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Operations\\Dashboard\\Transport\\Api\\DashboardResource', 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '控制台', 'group' => '系统', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'dashboard.read', 'scope' => 'global']]]])
    ->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
