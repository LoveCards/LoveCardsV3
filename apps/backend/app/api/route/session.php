<?php

use think\facade\Route;

use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\api\middleware\SessionDebounce;
use app\api\middleware\LoginThrottle;
use app\api\middleware\CaptchaCheck;

use app\modules\Platform\Auth\Transport\Api\AuthPolicyResource;
use app\modules\Platform\Auth\Transport\Api\SessionResource;
use app\modules\Platform\Auth\Transport\Api\Recovery as RecoveryValidate;
use app\shared\Transport\Http\ApiResource;
use app\modules\Platform\Auth\Transport\Api\CurrentUserSessionResource;

// ─── 公开路由 ───
Route::post('session/login', 'User.Session/login')
    ->name('session.login')
    ->setOption('contract', ['request' => ['validate' => 'app\\modules\\Platform\\Auth\\Transport\\Api\\Session', 'scene' => 'login'], 'response' => ['resource' => 'app\\modules\\Platform\\Auth\\Transport\\Api\\AuthResource', 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '登录', 'group' => '认证', 'access' => ['auth' => 'public']])
    ->middleware(LoginThrottle::class);

Route::post('session/register', 'User.Session/register')
    ->name('session.register')
    ->setOption('contract', ['request' => ['validate' => 'app\\modules\\Platform\\Auth\\Transport\\Api\\Session', 'scene' => 'register'], 'response' => ['resource' => 'app\\modules\\Platform\\Auth\\Transport\\Api\\AuthResource', 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '注册', 'group' => '认证', 'access' => ['auth' => 'public']])
    ->middleware(SessionDebounce::class)->middleware(CaptchaCheck::class, ['type' => 'captcha']);

Route::post('session/guest', 'User.Session/guest')
    ->name('session.guest')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Platform\\Auth\\Transport\\Api\\AuthResource', 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '访客登录', 'group' => '认证', 'access' => ['auth' => 'public']])
    ->middleware(LoginThrottle::class);

Route::post('session/captcha', 'User.Session/captcha')
    ->name('session.captcha')
    ->setOption('contract', ['request' => ['validate' => 'app\\modules\\Platform\\Auth\\Transport\\Api\\Session', 'scene' => 'captcha'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
    ->setOption('meta', ['name' => '获取验证码', 'group' => '认证', 'access' => ['auth' => 'public']])
    ->middleware(SessionDebounce::class);

Route::get('session/policy', 'User.Session/policy')
    ->name('session.policy')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => AuthPolicyResource::class, 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '认证策略', 'group' => '认证', 'access' => ['auth' => 'public']]);

Route::post('session/recovery/request', 'Platform.Recovery/issue')->name('session.recovery.request')
    ->setOption('contract', ['request' => ['validate' => RecoveryValidate::class, 'scene' => 'request'], 'response' => ['resource' => ApiResource::class, 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => '申请账号恢复', 'group' => '认证', 'access' => ['auth' => 'public']])->middleware(SessionDebounce::class);
Route::post('session/recovery/reset', 'Platform.Recovery/reset')->name('session.recovery.reset')
    ->setOption('contract', ['request' => ['validate' => RecoveryValidate::class, 'scene' => 'reset'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
    ->setOption('meta', ['name' => '完成账号恢复', 'group' => '认证', 'access' => ['auth' => 'public']])->middleware(SessionDebounce::class);

// ─── 需要 token 的路由 ───
Route::group('session', function () {
    Route::post('logout', 'User.Session/logout')
    ->name('session.logout')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '登出', 'group' => '认证', 'access' => ['auth' => 'required']]);

    Route::get('check', 'User.Session/check')
    ->name('session.check')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => SessionResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => 'Token校验', 'group' => '认证', 'access' => ['auth' => 'required']]);
})->middleware(JwtAuthCheck::class);

Route::get('me/sessions', 'User.Session/listSessions')->name('session.listSessions')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => CurrentUserSessionResource::class, 'shape' => 'collection', 'status' => 200]])
    ->setOption('meta', ['name' => '我的登录会话', 'group' => '认证', 'access' => ['auth' => 'required']])
    ->middleware(JwtAuthCheck::class);
Route::delete('me/sessions/:sessionId', 'User.Session/revokeSession')->name('session.revokeSession')
    ->pattern(['sessionId' => '[a-f0-9]{32}'])
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
    ->setOption('meta', ['name' => '撤销我的登录会话', 'group' => '认证', 'access' => ['auth' => 'required']])
    ->middleware(JwtAuthCheck::class);
Route::post('me/sessions/revoke-others', 'User.Session/revokeOthers')->name('session.revokeOthers')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
    ->setOption('meta', ['name' => '撤销其他登录会话', 'group' => '认证', 'access' => ['auth' => 'required']])
    ->middleware(JwtAuthCheck::class);
