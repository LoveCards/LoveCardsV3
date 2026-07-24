<?php

namespace app\api\application\Auth;

use app\api\ApiException;
use app\common\contract\TokenService;

final class RegisterUser
{
    private $tokens;
    private $users;

    public function __construct(TokenService $tokens, UserRepository $users)
    {
        $this->tokens = $tokens;
        $this->users = $users;
    }

    public function execute(
        string $number,
        string $username,
        string $email,
        string $phone,
        string $password
    ): array {
        if ($password === '') {
            throw ApiException::badRequest('密码不得为空', ApiException::CODE_PARAM_INVALID);
        }

        if ($this->users->contactExists($email, $phone)) {
            throw ApiException::badRequest('邮箱或手机号已存在', ApiException::CODE_USER_ALREADY_EXISTS);
        }

        $user = $this->users->create(
            $number,
            $username,
            $email,
            $phone,
            password_hash($password, PASSWORD_DEFAULT),
            [config('system.system_roles.user')],
            0
        );

        return [
            'user' => $user,
            'token' => $this->tokens->sign(['uid' => $user->id()]),
        ];
    }
}
