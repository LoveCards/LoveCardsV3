<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;

Route::group('config', function () {
    Route::get('', 'System.Config/index')
    ->name('config.list')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Config\\Transport\\Api\\ConfigResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '获取配置', 'group' => '配置', 'caps' => ['config.read']]);

    Route::post('', 'System.Config/update')
    ->name('config.update')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '保存配置', 'group' => '配置', 'caps' => ['config.update']]);

    Route::get('groups', 'System.Config/groups')
    ->name('config.groups')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Config\\Transport\\Api\\ConfigResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '配置组列表', 'group' => '配置', 'caps' => ['config.read']]);

    Route::post('init', 'System.Config/init')
    ->name('config.init')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Config\\Transport\\Api\\ConfigResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '初始化配置', 'group' => '配置', 'caps' => ['config.init']]);

    Route::post('register', 'System.Config/register')
    ->name('config.register')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Config\\Transport\\Api\\ConfigResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '注册配置', 'group' => '配置', 'caps' => ['config.register']]);

    Route::post('reload', 'System.Config/reload')
    ->name('config.reload')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '重载配置', 'group' => '配置', 'caps' => ['config.reload']]);

    Route::delete('', 'System.Config/delete')
    ->name('config.delete')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '删除配置组', 'group' => '配置', 'caps' => ['config.deleteKey']]);

    Route::delete('key', 'System.Config/deleteKey')
    ->name('config.deleteKey')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '删除配置键', 'group' => '配置', 'caps' => ['config.deleteKey']]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
