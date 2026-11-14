<?php

namespace app\modules\Platform\Auth\Application;

use app\api\ApiException;
use app\modules\Platform\Auth\Application\LoginUser;
use app\modules\Platform\Auth\Contract\UserRepository;
use app\modules\Platform\Auth\Contract\TokenService;
use app\modules\Platform\Captcha\Public\Captcha;
use app\modules\Platform\Config\Public\Config as ConfigService;
use app\modules\Platform\User\Public\UserStatus;

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
                'available' => Captcha::isReady('captcha'),
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

    /**
     * 访客登录（一次性服务端身份）：账号凭据完全随机，Token 本身承载连续性
     * （客户端 tokenStore 持久化）。历史方案从 IP 派生邮箱并使用静态口令，
     * 既可被知道出口 IP 者接管，也会让共享出口 IP 的不同访客串号。
     * $clientIp 仅保留调用点签名（审计与后续风控用），绝不作为身份材料。
     */
    public function guest(string $clientIp): array
    {
        $guestRoleId = (int) config('system.system_roles.guest');

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $user = $this->users->create(
                    $this->generateNumber(),
                    'GUEST' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 5)),
                    'g' . bin2hex(random_bytes(8)) . '@g.com',
                    '',
                    password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    [$guestRoleId],
                    UserStatus::ACTIVE
                );

                return ['user' => $user, 'token' => $this->tokens->sign(['uid' => $user->id()])];
            } catch (Throwable $exception) {
                // 随机用户名/编号撞唯一键的概率极低，重试一次后原样上抛。
                if ($attempt === 1) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('guest mint is unreachable');
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
