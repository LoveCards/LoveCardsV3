<?php

namespace app\modules\Platform\Auth\Application;

use app\api\ApiException;
use app\modules\Platform\Auth\Contract\TokenService;
use app\modules\Platform\Auth\Contract\UserRepository;
use app\modules\Platform\User\Public\UserStatus;

final class LoginUser
{
    private $tokens;
    private $users;

    public function __construct(TokenService $tokens, UserRepository $users)
    {
        $this->tokens = $tokens;
        $this->users = $users;
    }

    public function execute(string $account, string $password): array
    {
        $user = $this->users->findByAccount($account);

        if (!$user) {
            throw ApiException::unauthorized('用户不存在', ApiException::CODE_USER_NOT_FOUND);
        }

        if (!UserStatus::canAuthenticate($user->status())) {
            throw ApiException::forbidden('您的账户已被封禁或未激活', ApiException::CODE_USER_BANNED);
        }

        // 访客角色账号是一次性服务端身份：口令通道一律拒绝，
        // 同时中和存量使用弱口令哈希的访客账号。
        $guestRoleId = (int) config('system.system_roles.guest');
        if (in_array($guestRoleId, array_map('intval', $user->roleIds()), true)) {
            throw ApiException::forbidden('访客账号不支持密码登录', ApiException::CODE_PERMISSION_DENIED);
        }

        if (!password_verify($password, $user->passwordHash())) {
            throw ApiException::unauthorized('密码不匹配', ApiException::CODE_PASSWORD_MISMATCH);
        }

        return [
            'user' => $user,
            'token' => $this->tokens->sign(['uid' => $user->id()]),
        ];
    }
}
