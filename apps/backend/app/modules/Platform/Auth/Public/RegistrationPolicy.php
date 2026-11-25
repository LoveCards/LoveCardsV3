<?php

declare(strict_types=1);

namespace app\modules\Platform\Auth\Public;

final class RegistrationPolicy
{
    public const MODES = ['email_or_phone', 'email', 'phone'];
    public const RESEND_SECONDS = 60;

    public function __construct(private array $settings)
    {
    }

    public function accountTypes(): array
    {
        return match ($this->settings['registration_mode'] ?? '') {
            'email' => ['email'],
            'phone' => ['phone'],
            'email_or_phone' => ['email', 'phone'],
            // 旧站首次保存注册策略前，保留原本的邮箱验证门槛。
            '' => $this->requiresCode('email') ? ['email'] : ['email', 'phone'],
            default => [],
        };
    }

    public function requiresCode(string $type): bool
    {
        return (bool) ($this->settings[$type === 'email' ? 'captcha' : 'registration_sms_verification'] ?? false);
    }

    public static function channel(string $type): string
    {
        return $type === 'email' ? 'smtp' : 'sms';
    }

    public static function contactType(string $account): ?string
    {
        if (filter_var($account, FILTER_VALIDATE_EMAIL)) return 'email';
        return preg_match('/^1[3-9][0-9]{9}$/D', $account) === 1 ? 'phone' : null;
    }
}
