<?php

namespace app\modules\Platform\Auth\Application;

use app\api\ApiException;
use app\modules\Platform\Auth\Application\LoginUser;
use app\modules\Platform\Auth\Contract\UserRepository;
use app\modules\Platform\Auth\Public\RegistrationPolicy;
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

    public function visitorModeEnabled(): bool
    {
        return (bool) ConfigService::get('core.visitor_mode');
    }

    public function policy(): array
    {
        $registration = $this->registrationPolicy();
        $captchaEnabled = (bool) ConfigService::get('captcha.captcha_enabled', false);
        $captchaDriver = (string) ConfigService::get('captcha.default_captcha_driver', 'geetest_v4');
        $methods = $registration->accountTypes();
        $emailRequired = in_array('email', $methods, true) && $registration->requiresCode('email');
        $smsRequired = in_array('phone', $methods, true) && $registration->requiresCode('phone');

        return [
            'registration' => [
                'enabled' => $methods !== [],
                'account_types' => $methods,
                'email_verification_required' => $emailRequired,
                'email_verification_available' => $emailRequired && $this->registrationCodeAvailable('email'),
                'sms_verification_required' => $smsRequired,
                'sms_verification_available' => $smsRequired && $this->registrationCodeAvailable('phone'),
                'resend_after' => RegistrationPolicy::RESEND_SECONDS,
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

    public function registrationPolicy(): RegistrationPolicy
    {
        $settings = ConfigService::getGroup('user');
        // 旧部署可能通过环境变量覆盖邮箱验证。
        $settings['captcha'] ??= ConfigService::get('user.captcha', false);
        return new RegistrationPolicy($settings);
    }

    public function registrationContact(string $input, RegistrationPolicy $policy): array
    {
        $account = trim($input);
        $type = RegistrationPolicy::contactType($account);
        if ($type === null) {
            throw ApiException::validation(['account' => '请输入有效邮箱或中国大陆手机号。'], '注册账号格式不正确', 'registration-contact-invalid');
        }
        if (!in_array($type, $policy->accountTypes(), true)) {
            throw ApiException::validation(['account' => '当前站点未开放此注册方式。'], '注册方式不可用', 'registration-method-not-allowed');
        }
        return [$account, $type];
    }

    public function sendCaptcha(string $input, string $ip): void
    {
        $policy = $this->registrationPolicy();
        [$account, $type] = $this->registrationContact($input, $policy);
        if (!$policy->requiresCode($type)) {
            throw ApiException::validation(['account' => '当前注册方式无需验证码。'], '注册策略已变更', 'registration-code-not-required');
        }
        $this->assertRegistrationCodeAvailable($type);
        $result = Captcha::generate('code', [
            'to' => $account,
            'channel' => RegistrationPolicy::channel($type),
            'scene' => 'Auth',
            'ttl' => 300,
            'resend_after' => RegistrationPolicy::RESEND_SECONDS,
            'ip' => $ip,
        ]);
        if (!($result['status'] ?? false)) {
            throw ApiException::capabilityUnavailable('验证码发送失败，请稍后重试', 'registration-code-delivery-failed');
        }
    }

    public function assertRegistrationCodeAvailable(string $type): void
    {
        if (!$this->registrationCodeAvailable($type)) {
            throw ApiException::capabilityUnavailable('该注册方式的验证码服务尚未就绪', 'registration-code-unavailable');
        }
    }

    public function verifyCaptcha(string $account, string $code): bool
    {
        return Captcha::verifyDetailed('code', ['key' => $account, 'code' => $code, 'scene' => 'Auth']) === 'valid';
    }

    private function registrationCodeAvailable(string $type): bool
    {
        return Captcha::codeAvailable(RegistrationPolicy::channel($type), $this->captchaEnabled(), (string) ConfigService::get('captcha.default_code_driver', 'smtp_code'));
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
        return (bool) ConfigService::get('captcha.captcha_enabled', false);
    }

    public function verifyHumanVerification(array $params): bool
    {
        $driver = (string) ConfigService::get('captcha.default_captcha_driver', 'geetest_v4');
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
