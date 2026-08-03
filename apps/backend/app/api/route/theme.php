<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;

// 公开主题配置（必须在 auth group 之前定义）
Route::get('theme/config', 'Theme.ThemeManager/config')
    ->name('theme.publicConfig')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Theme\\Transport\\Api\\ThemeResource', 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '公开主题配置', 'group' => '主题', 'public' => true]);

Route::group('theme', function () {
    Route::get('list', 'Theme.ThemeManager/list')
    ->name('theme.list')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Theme\\Transport\\Api\\ThemeResource', 'shape' => 'collection', 'status' => 200]])
        ->setOption('meta', ['name' => '主题列表', 'group' => '主题', 'caps' => ['theme.read']]);

    Route::post('upload', 'Theme.ThemeManager/upload')
    ->name('theme.upload')
    ->setOption('contract', ['request' => ['validate' => 'app\\modules\\Theme\\Transport\\Api\\Upload', 'scene' => 'default'], 'response' => ['resource' => 'app\\modules\\Theme\\Transport\\Api\\ThemeResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '上传主题', 'group' => '主题', 'caps' => ['theme.upload']]);

    Route::post('activate', 'Theme.ThemeManager/activate')
    ->name('theme.activate')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '切换主题', 'group' => '主题', 'caps' => ['theme.activate']]);

    Route::put('config', 'Theme.ThemeManager/updateConfig')
    ->name('theme.updateConfig')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '更新配置', 'group' => '主题', 'caps' => ['theme.update']]);

    Route::post('freeze', 'Theme.ThemeManager/freezeConfig')
    ->name('theme.freeze')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '固化配置', 'group' => '主题', 'caps' => ['theme.freeze']]);

    Route::delete('delete', 'Theme.ThemeManager/delete')
    ->name('theme.delete')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '删除主题', 'group' => '主题', 'caps' => ['theme.delete']]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
