<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\api\middleware\SessionDebounce;
use app\api\middleware\CaptchaCheck;

// ─── 公开路由 ───
Route::post('session/login', 'User.Session/login')
    ->name('session.login')
    ->setOption('contract', ['request' => ['validate' => 'app\\modules\\Platform\\Auth\\Transport\\Api\\Session', 'scene' => 'login'], 'response' => ['resource' => 'app\\modules\\Platform\\Auth\\Transport\\Api\\AuthResource', 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '登录', 'group' => '认证', 'public' => true]);

Route::post('session/register', 'User.Session/register')
    ->name('session.register')
    ->setOption('contract', ['request' => ['validate' => 'app\\modules\\Platform\\Auth\\Transport\\Api\\Session', 'scene' => 'register'], 'response' => ['resource' => 'app\\modules\\Platform\\Auth\\Transport\\Api\\AuthResource', 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '注册', 'group' => '认证', 'public' => true])
    ->middleware(SessionDebounce::class)->middleware(CaptchaCheck::class, ['type' => 'captcha']);

Route::post('session/guest', 'User.Session/guest')
    ->name('session.guest')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Auth\\Transport\\Api\\AuthResource', 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '访客登录', 'group' => '认证', 'public' => true]);

Route::post('session/captcha', 'User.Session/captcha')
    ->name('session.captcha')
    ->setOption('contract', ['request' => ['validate' => 'app\\modules\\Platform\\Auth\\Transport\\Api\\Session', 'scene' => 'captcha'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
    ->setOption('meta', ['name' => '获取验证码', 'group' => '认证', 'public' => true])
    ->middleware(SessionDebounce::class);

// ─── 需要 token 的路由 ───
Route::group('session', function () {
    Route::post('logout', 'User.Session/logout')
    ->name('session.logout')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '登出', 'group' => '认证']);

    Route::get('check', 'User.Session/check')
    ->name('session.check')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\shared\\Transport\\Http\\ApiResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => 'Token校验', 'group' => '认证']);
})->middleware(JwtAuthCheck::class);
