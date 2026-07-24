<?php

namespace app\api\service\User;

use app\api\ApiException;
use app\api\application\Auth\LoginUser;
use app\api\application\Auth\UserRepository;
use app\common\contract\TokenService;
use app\api\service\Captcha\Captcha;

class Session
{
    private $tokens;
    private $users;
    private $loginUser;

    public function __construct(TokenService $tokens, UserRepository $users, LoginUser $loginUser)
    {
        $this->tokens = $tokens;
        $this->users = $users;
        $this->loginUser = $loginUser;
    }

    /**
     * 创建用户（内部方法，供 guest 调用）
     */
    private function createUser(string $number, string $username, string $email, string $phone, string $password, array $rolesId, int $status): array
    {
        $user = $this->users->create(
            $number,
            $username,
            $email,
            $phone,
            password_hash($password, PASSWORD_DEFAULT),
            $rolesId,
            $status
        );

        $token = $this->tokens->sign(['uid' => $user->id()]);

        return ['user' => $user, 'token' => $token];
    }

    public function guest(string $ip): array
    {
        $timekey = date('YmdH');
        $account = strtoupper(substr(md5($ip . $timekey), 0, 9)) . '@g.com';
        $password = '123456';
        $username = 'GUEST' . strtoupper(substr(md5($account . $password . time()), 0, 5));
        $number = $this->generateNumber();

        $accountMap = $this->resolveAccountType($account);

        try {
            return $this->loginUser->execute($account, $password);
        } catch (ApiException $e) {
            if ($e->getCode() !== ApiException::CODE_USER_NOT_FOUND) {
                throw $e;
            }
            return $this->createUser(
                $number,
                $username,
                $accountMap['email'],
                $accountMap['phone'],
                $password,
                [config('system.system_roles.guest')],
                0
            );
        }
    }

    public function sendCaptcha(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ApiException::badRequest('目前仅支持邮箱验证', ApiException::CODE_PARAM_INVALID);
        }

        Captcha::generate('code', ['to' => $email, 'scene' => 'Auth', 'ttl' => 300]);
    }

    public function verifyCaptcha(string $email, string $code): bool
    {
        return Captcha::verify('code', ['key' => $email, 'code' => $code, 'scene' => 'Auth']);
    }

    public function deleteCaptcha(string $email): void
    {
        $driver = Captcha::driver('code');
        if (method_exists($driver, 'delete')) {
            $driver->delete($email, 'Auth');
        }
    }

    public function resolveAccountType(string $account, string $default = ''): array
    {
        if (preg_match('/^\d{11}$/', $account)) {
            return ['phone' => $account, 'email' => $default, 'number' => $default];
        }
        if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
            return ['phone' => $default, 'email' => $account, 'number' => $default];
        }
        return ['phone' => $default, 'email' => $default, 'number' => $account];
    }

    public function generateNumber(): string
    {
        $characters = '0123456789';
        $number = '';
        for ($i = 0; $i < 10; $i++) {
            $number .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $number;
    }
}
