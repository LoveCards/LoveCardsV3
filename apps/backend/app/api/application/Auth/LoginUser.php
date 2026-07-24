<?php

namespace app\api\application\Auth;

use app\api\ApiException;
use app\common\contract\TokenService;

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

        if ($user->status() != 0 && $user->status() != 2) {
            throw ApiException::forbidden('您的账户已被封禁或未激活', ApiException::CODE_USER_BANNED);
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
