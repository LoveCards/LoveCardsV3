<?php

namespace app\modules\Platform\Auth\Application;

use app\api\ApiException;
use app\modules\Platform\Auth\Application\LoginUser;
use app\modules\Platform\Auth\Contract\UserRepository;
use app\modules\Platform\Auth\Contract\TokenService;
use app\modules\Platform\Captcha\Public\Captcha;
use app\modules\Platform\Config\Public\Config as ConfigService;

class Session
{
    public function captchaEnabled(): bool
    {
        return (bool) ConfigService::get('captcha.code_enabled', true);
    }

    public function registrationEmailVerificationRequired(): bool
    {
        return (bool) ConfigService::get('user.captcha');
    }

    public function visitorModeEnabled(): bool
    {
        return (bool) ConfigService::get('core.visitor_mode');
    }

    public function policy(): array
    {
        $emailVerification = (bool) ConfigService::get('user.captcha');
        $codeEnabled = (bool) ConfigService::get('captcha.code_enabled', true);
        $captchaEnabled = (bool) ConfigService::get('captcha.captcha_enabled', false);
        $captchaDriver = (string) ConfigService::get('captcha.default_captcha_driver', 'geetest_v4');

        // Geetest is only usable after its credentials have been activated.
        // The image driver has no separate activation flag.
        if ($captchaDriver === 'geetest_v4' && !(bool) ConfigService::get('captcha_geetest_v4.status', false)) {
            $captchaEnabled = false;
        }

        return [
            'registration' => [
                'enabled' => true,
                'account_types' => $emailVerification ? ['email'] : ['email', 'phone', 'username'],
                'email_verification_required' => $emailVerification,
                'email_verification_available' => $codeEnabled,
            ],
            'guest' => ['enabled' => $this->visitorModeEnabled()],
            'human_verification' => [
                'enabled' => $captchaEnabled,
                'driver' => $captchaDriver,
            ],
        ];
    }
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

    public function sendCaptcha(string $email): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ApiException::badRequest('目前仅支持邮箱验证', ApiException::CODE_PARAM_INVALID);
        }

        $result = Captcha::generate('code', ['to' => $email, 'scene' => 'Auth', 'ttl' => 300]);
        if (!($result['status'] ?? false)) {
            throw ApiException::badRequest((string) ($result['msg'] ?? '验证码发送失败'), ApiException::CODE_PARAM_INVALID);
        }

        return $result;
    }

    public function verifyCaptcha(string $email, string $code): bool
    {
        return Captcha::verify('code', ['key' => $email, 'code' => $code, 'scene' => 'Auth']);
    }

    public function deleteCaptcha(string $email): void
    {
        Captcha::delete('code', $email, 'Auth');
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

    public function humanVerificationEnabled(): bool
    {
        return (bool) ($this->policy()['human_verification']['enabled'] ?? false);
    }

    public function verifyHumanVerification(array $params): bool
    {
        $driver = (string) ($this->policy()['human_verification']['driver'] ?? '');
        if ($driver === 'gd_image') {
            return Captcha::verify('captcha', [
                'image_id' => (string) ($params['captcha_id'] ?? ''),
                'code' => (string) ($params['captcha_code'] ?? ''),
            ]);
        }
        return Captcha::verify('captcha', [
            'lot_number' => (string) ($params['lot_number'] ?? ''),
            'captcha_output' => (string) ($params['captcha_output'] ?? ''),
            'pass_token' => (string) ($params['pass_token'] ?? ''),
            'gen_time' => (string) ($params['gen_time'] ?? ''),
        ]);
    }

    public function logout(string $token): void
    {
        if ($token !== '') {
            $this->tokens->invalidate($token);
        }
    }
}
