<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;

Route::group('admin/captcha', function () {
    Route::get('types', 'Captcha.Captcha/types')
    ->name('captcha.types')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Captcha\\Transport\\Api\\CaptchaResource', 'shape' => 'collection', 'status' => 200]])
        ->setOption('meta', ['name' => '验证驱动列表', 'group' => '验证', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'captcha.read', 'scope' => 'global']]]]);

    Route::get('drivers', 'Captcha.Captcha/drivers')
    ->name('captcha.drivers')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Captcha\\Transport\\Api\\CaptchaResource', 'shape' => 'collection', 'status' => 200]])
        ->setOption('meta', ['name' => '验证驱动详情', 'group' => '验证', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'captcha.read', 'scope' => 'global']]]]);

    Route::get(':slug/meta', 'Captcha.Captcha/meta')
    ->name('captcha.meta')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Captcha\\Transport\\Api\\CaptchaResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '驱动配置信息', 'group' => '验证', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'captcha.read', 'scope' => 'global']]]]);

    Route::post('install', 'Captcha.Captcha/install')
    ->name('captcha.install')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Captcha\\Transport\\Api\\CaptchaResource', 'shape' => 'collection', 'status' => 200]])
        ->setOption('meta', ['name' => '安装验证驱动', 'group' => '验证', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'captcha.install', 'scope' => 'global']]]]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

// 公开验证配置
Route::get('captcha/challenge', 'Captcha.Captcha/challenge')
    ->name('captcha.challenge')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Captcha\\Transport\\Api\\CaptchaChallengeResource', 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '人机验证挑战', 'group' => '验证', 'access' => ['auth' => 'public']]);

Route::get('captcha/config', 'Captcha.Captcha/config')
    ->name('captcha.config')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Captcha\\Transport\\Api\\CaptchaResource', 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '验证配置', 'group' => '验证', 'access' => ['auth' => 'public']]);
