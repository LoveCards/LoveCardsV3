<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\modules\Platform\Config\Transport\Api\Config as ConfigValidate;
use app\modules\Platform\Config\Transport\Api\RedisConnectionTestResource;

Route::group('admin/config', function () {
    Route::post('redis/test', 'System.Config/testRedis')
        ->name('config.redisTest')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => RedisConnectionTestResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '测试 Redis 连接', 'group' => '配置', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'operations.runtime.configure', 'scope' => 'global']]]]);
    Route::get('', 'System.Config/index')
    ->name('config.list')
    ->setOption('contract', ['request' => ['validate' => ConfigValidate::class, 'scene' => 'list'], 'response' => ['resource' => 'app\\modules\\Platform\\Config\\Transport\\Api\\ConfigResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '获取配置', 'group' => '配置', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'config.read', 'scope' => 'global']]]]);

    Route::post('', 'System.Config/update')
    ->name('config.update')
    ->setOption('contract', ['request' => ['validate' => ConfigValidate::class, 'scene' => 'update'], 'response' => ['resource' => 'app\\modules\\Platform\\Config\\Transport\\Api\\ConfigResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '保存配置', 'group' => '配置', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'config.update', 'scope' => 'global']]]]);

    Route::get('groups', 'System.Config/groups')
    ->name('config.groups')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Config\\Transport\\Api\\ConfigResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '配置组列表', 'group' => '配置', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'config.read', 'scope' => 'global']]]]);

    Route::post('init', 'System.Config/init')
    ->name('config.init')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Config\\Transport\\Api\\ConfigResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '初始化配置', 'group' => '配置', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'config.init', 'scope' => 'global']]]]);

    Route::post('register', 'System.Config/register')
    ->name('config.register')
    ->setOption('contract', ['request' => ['validate' => ConfigValidate::class, 'scene' => 'register'], 'response' => ['resource' => 'app\\modules\\Platform\\Config\\Transport\\Api\\ConfigResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '注册配置', 'group' => '配置', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'config.register', 'scope' => 'global']]]]);

    Route::post('reload', 'System.Config/reload')
    ->name('config.reload')
    ->setOption('contract', ['request' => ['validate' => ConfigValidate::class, 'scene' => 'reload'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '重载配置', 'group' => '配置', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'config.reload', 'scope' => 'global']]]]);

    Route::delete('', 'System.Config/delete')
    ->name('config.delete')
    ->setOption('contract', ['request' => ['validate' => ConfigValidate::class, 'scene' => 'delete'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '删除配置组', 'group' => '配置', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'config.deleteKey', 'scope' => 'global']]]]);

    Route::delete('key', 'System.Config/deleteKey')
    ->name('config.deleteKey')
    ->setOption('contract', ['request' => ['validate' => ConfigValidate::class, 'scene' => 'deleteKey'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '删除配置键', 'group' => '配置', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'config.deleteKey', 'scope' => 'global']]]]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
