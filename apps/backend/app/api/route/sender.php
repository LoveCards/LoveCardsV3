<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\shared\Transport\Validation\ChannelTestInput;

Route::group('admin/sender', function () {
    Route::get('types', 'Sender.Sender/types')
    ->name('sender.types')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Sender\\Transport\\Api\\SenderResource', 'shape' => 'collection', 'status' => 200]])
        ->setOption('meta', ['name' => '消息驱动列表', 'group' => '消息', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'sender.read', 'scope' => 'global']]]]);

    Route::get(':type/meta', 'Sender.Sender/meta')
    ->name('sender.meta')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Sender\\Transport\\Api\\SenderResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '驱动配置信息', 'group' => '消息', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'sender.read', 'scope' => 'global']]]]);

    Route::post('install', 'Sender.Sender/install')
    ->name('sender.install')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Sender\\Transport\\Api\\SenderResource', 'shape' => 'collection', 'status' => 200]])
        ->setOption('meta', ['name' => '安装消息驱动', 'group' => '消息', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'sender.install', 'scope' => 'global']]]]);

    Route::get('channels', 'Sender.Sender/channels')
    ->name('sender.channels')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Sender\\Transport\\Api\\SenderResource', 'shape' => 'collection', 'status' => 200]])
        ->setOption('meta', ['name' => '消息渠道列表', 'group' => '消息', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'sender.read', 'scope' => 'global']]]]);

    Route::get('templates', 'Sender.Sender/templates')
    ->name('sender.templates')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Sender\\Transport\\Api\\SenderResource', 'shape' => 'collection', 'status' => 200]])
        ->setOption('meta', ['name' => '消息模板列表', 'group' => '消息', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'sender.read', 'scope' => 'global']]]]);

    Route::post('test-channel', 'Sender.Sender/testChannel')
    ->name('sender.testChannel')
    ->setOption('contract', ['request' => ['validate' => ChannelTestInput::class, 'scene' => 'test'], 'response' => ['resource' => 'app\\modules\\Platform\\Sender\\Transport\\Api\\SenderResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '测试消息渠道', 'group' => '消息', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'sender.test', 'scope' => 'global']]]]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
