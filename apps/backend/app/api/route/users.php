<?php

use think\facade\Route;
use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\modules\Platform\User\Transport\Api\Profile;
use app\modules\Platform\User\Transport\Api\ProfileResource;
use app\modules\Platform\User\Transport\Api\EmailCaptchaResource;
use app\modules\Platform\User\Transport\Api\Users as UsersValidate;
use app\modules\Platform\User\Transport\Api\UserResource;
use app\modules\Platform\User\Transport\Api\MentionCandidates as MentionCandidatesValidate;
use app\modules\Platform\User\Transport\Api\MentionCandidateResource;
use app\shared\Authorization\Scope;
use app\shared\Transport\Http\ApiResource;
use app\shared\Transport\Validation\PaginationInput;
use app\shared\Transport\Validation\BatchOperationInput;

$required = static fn (array $allOf = [], array $anyOf = []): array => [
    'auth' => 'required', 'allOf' => $allOf, 'anyOf' => $anyOf,
];
$grant = static fn (string $capability, string $scope): array => compact('capability', 'scope');

Route::group('me', function () use ($required, $grant) {
    Route::get('', 'User.Profile/get')->name('users.me.get')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => ProfileResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '我的信息', 'group' => '用户', 'access' => $required([$grant('users.read', Scope::OWN)])]);
    Route::patch('', 'User.Profile/update')->name('users.me.update')
        ->setOption('contract', ['request' => ['validate' => Profile::class, 'scene' => 'update'], 'response' => ['resource' => ProfileResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '编辑我的信息', 'group' => '用户', 'access' => $required([$grant('users.update', Scope::OWN)])]);
    Route::post('password', 'User.Profile/password')->name('users.me.password')
        ->setOption('contract', ['request' => ['validate' => Profile::class, 'scene' => 'password'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '修改密码', 'group' => '用户', 'access' => $required([$grant('users.update', Scope::OWN)])]);
    Route::post('email', 'User.Profile/email')->name('users.me.email')
        ->setOption('contract', ['request' => ['validate' => Profile::class, 'scene' => 'email'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '绑定邮箱', 'group' => '用户', 'access' => $required([$grant('users.update', Scope::OWN)])]);
    Route::post('email-captcha', 'User.Profile/emailCaptcha')->name('users.me.emailCaptcha')
        ->setOption('contract', ['request' => ['validate' => Profile::class, 'scene' => 'emailCaptcha'], 'response' => ['resource' => EmailCaptchaResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '邮箱验证码', 'group' => '用户', 'access' => $required([$grant('users.update', Scope::OWN)])]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);

// 普通用户的 @ 提及候选。只经过登录校验，隐私、屏蔽和账号状态由业务层统一筛选。
Route::get('users/mention-candidates', 'User.Users/searchMentionCandidates')->name('users.searchMentionCandidates')
    ->setOption('contract', ['request' => ['validate' => MentionCandidatesValidate::class, 'scene' => 'search'], 'response' => ['resource' => MentionCandidateResource::class, 'shape' => 'paginated', 'status' => 200]])
    ->setOption('meta', ['name' => '提及候选用户', 'group' => '用户', 'access' => ['auth' => 'required']])
    ->middleware(JwtAuthCheck::class);

Route::group('admin/users', function () use ($required, $grant) {
    $admin = $grant('admin.access', Scope::GLOBAL);
    Route::get('', 'User.Users/list')->name('users.listManaged')
        ->setOption('contract', ['request' => ['validate' => PaginationInput::class, 'scene' => 'AdminUsers'], 'response' => ['resource' => UserResource::class, 'shape' => 'paginated', 'status' => 200]])
        ->setOption('meta', ['name' => '管理用户列表', 'group' => '用户', 'access' => $required([$admin, $grant('users.read', Scope::ALL)])]);
    Route::get(':id', 'User.Users/get')->name('users.getManaged')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => UserResource::class, 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => '管理用户详情', 'group' => '用户', 'access' => $required([$admin, $grant('users.read', Scope::ALL)])]);
    Route::patch(':id', 'User.Users/update')->name('users.updateManaged')
        ->setOption('contract', ['request' => ['validate' => UsersValidate::class, 'scene' => 'adminUpdate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '管理用户编辑', 'group' => '用户', 'access' => $required([$admin, $grant('users.update', Scope::ALL)])]);
    Route::patch(':id/roles', 'User.Users/assignRoles')->name('users.assignRoles')
        ->setOption('contract', ['request' => ['validate' => UsersValidate::class, 'scene' => 'assignRoles'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '分配用户角色', 'group' => '用户', 'access' => $required([$admin, $grant('users.roles.assign', Scope::GLOBAL)])]);
    Route::delete(':id', 'User.Users/delete')->name('users.deleteManaged')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '管理用户删除', 'group' => '用户', 'access' => $required([$admin, $grant('users.delete', Scope::ALL)])]);
    Route::post('batch', 'User.Users/batch')->name('users.batchManaged')
        ->setOption('contract', ['request' => ['validate' => BatchOperationInput::class, 'scene' => 'AdminBatchOperate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => '管理用户批量操作', 'group' => '用户', 'access' => $required([$admin])]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
