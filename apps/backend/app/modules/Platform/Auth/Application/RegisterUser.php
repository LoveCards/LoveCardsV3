<?php

namespace app\modules\Platform\Auth\Application;

use app\api\ApiException;
use app\modules\Platform\Auth\Contract\TokenService;
use app\modules\Platform\Auth\Contract\UserRepository;

final class RegisterUser
{
    private $tokens;
    private $users;

    public function __construct(TokenService $tokens, UserRepository $users)
    {
        $this->tokens = $tokens;
        $this->users = $users;
    }

    public function register(array $params, Session $session): array
    {
        $policy = $session->registrationPolicy();
        [$account, $type] = $session->registrationContact($params['account'], $policy);
        if ($session->humanVerificationEnabled() && !$session->verifyHumanVerification($params)) {
            throw ApiException::unauthorized('人机验证失败');
        }
        if ($policy->requiresCode($type)) {
            $session->assertRegistrationCodeAvailable($type);
            $code = trim((string) ($params['code'] ?? ''));
            if ($code === '' || !$session->verifyCaptcha($account, $code)) {
                throw ApiException::validation(['code' => '请输入有效验证码，过期后请重新发送。'], '注册验证码无效', 'registration-code-invalid');
            }
        }

        return $this->execute(
            $session->generateNumber(),
            'USER' . strtoupper(bin2hex(random_bytes(8))),
            $type === 'email' ? $account : '',
            $type === 'phone' ? $account : '',
            $params['password']
        );
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
