<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\shared\Transport\Validation\ChannelTestInput;

Route::group('admin/storage', function () {
    Route::get('types', 'Storage.Storage/types')
    ->name('storage.types')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Storage\\Transport\\Api\\StorageResource', 'shape' => 'collection', 'status' => 200]])
        ->setOption('meta', ['name' => '存储驱动列表', 'group' => '存储', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'storage.read', 'scope' => 'global']]]]);

    Route::get(':type/meta', 'Storage.Storage/meta')
    ->name('storage.meta')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Storage\\Transport\\Api\\StorageResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '驱动配置信息', 'group' => '存储', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'storage.read', 'scope' => 'global']]]]);

    Route::post('install', 'Storage.Storage/install')
    ->name('storage.install')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Storage\\Transport\\Api\\StorageResource', 'shape' => 'collection', 'status' => 200]])
        ->setOption('meta', ['name' => '安装存储驱动', 'group' => '存储', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'storage.install', 'scope' => 'global']]]]);

    Route::get('channels', 'Storage.Storage/channels')
    ->name('storage.channels')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Storage\\Transport\\Api\\StorageResource', 'shape' => 'collection', 'status' => 200]])
        ->setOption('meta', ['name' => '存储渠道列表', 'group' => '存储', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'storage.read', 'scope' => 'global']]]]);

    Route::post('test-channel', 'Storage.Storage/testChannel')
    ->name('storage.testChannel')
    ->setOption('contract', ['request' => ['validate' => ChannelTestInput::class, 'scene' => 'test'], 'response' => ['resource' => 'app\\modules\\Platform\\Storage\\Transport\\Api\\StorageResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '测试存储渠道', 'group' => '存储', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'storage.test', 'scope' => 'global']]]]);

    Route::get('channel-stats', 'Storage.Storage/channelStats')
    ->name('storage.channelStats')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Storage\\Transport\\Api\\StorageResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '渠道文件统计', 'group' => '存储', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'storage.read', 'scope' => 'global']]]]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
