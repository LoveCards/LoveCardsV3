<?php

namespace app\modules\Platform\Captcha\Public;

use app\modules\Platform\Captcha\Service\Captcha as CaptchaService;
use app\modules\Platform\Sender\Public\Sender;

final class Captcha
{
    public static function codeAvailable(string $channel, bool $enabled, string $driver): bool
    {
        return $enabled && $driver === 'smtp_code' && Sender::codeAvailable($channel);
    }
    public static function generate(string $type, array $params = [], ?string $driverSlug = null): array { return CaptchaService::generate($type, $params, $driverSlug); }
    public static function verify(string $type, array $params, ?string $driverSlug = null): bool { return CaptchaService::verify($type, $params, $driverSlug); }
    public static function isReady(string $type, ?string $driverSlug = null): bool { return CaptchaService::isReady($type, $driverSlug); }
    /** @return 'valid'|'expired'|'invalid' */
    public static function verifyDetailed(string $type, array $params, ?string $driverSlug = null): string { return CaptchaService::verifyDetailed($type, $params, $driverSlug); }
    public static function delete(string $type, string $key, string $scene = 'default', ?string $driverSlug = null): bool { return CaptchaService::delete($type, $key, $scene, $driverSlug); }
}
