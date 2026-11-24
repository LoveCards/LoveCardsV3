<?php
declare(strict_types=1);

namespace app\modules\Platform\Config\Public {
    final class Config
    {
        public static array $values = [];

        public static function get(string $key, mixed $default = null): mixed
        {
            return self::$values[$key] ?? $default;
        }

        public static function getGroup(string $group): array
        {
            return self::$values[$group] ?? [];
        }
    }
}

namespace app\shared\Infrastructure\Cache {
    final class CacheManager
    {
        public static array $values = [];

        public static function get(string $group, string $key): mixed
        {
            return self::$values[$key] ?? null;
        }

        public static function delete(string $key): void
        {
            unset(self::$values[$key]);
        }

        public static function set(string $group, string $key, mixed $value, int $ttl): void
        {
            self::$values[$key] = $value;
        }
    }
}

namespace app\shared\Infrastructure\Concurrency {
    final class NamedLock
    {
        public static function run(string $key, callable $operation): mixed { return $operation(); }
    }
}

namespace {
    require dirname(__DIR__, 2) . '/vendor/autoload.php';

    use app\modules\Platform\Auth\Application\Session;
    use app\modules\Platform\Auth\Public\RegistrationPolicy;
    use app\modules\Platform\Captcha\Service\Captcha;
    use app\modules\Platform\Captcha\Service\ChannelManager;
    use app\modules\Platform\Config\Public\Config;
    use app\shared\Infrastructure\Cache\CacheManager;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    };
    $settings = static function (array $captcha, array $other = []): void {
        Config::$values = ['captcha' => $captcha] + $other;
        $cache = new \ReflectionProperty(ChannelManager::class, 'settings');
        $cache->setAccessible(true);
        $cache->setValue(null, null);
    };
    $session = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();

    $settings(['captcha_enabled' => false, 'default_captcha_driver' => 'geetest_v4']);
    $assert(Captcha::verify('captcha', []) === true, 'disabled human verification must leave writes unchanged');

    $settings(['captcha_enabled' => true, 'default_captcha_driver' => 'geetest_v4'], [
        'captcha.captcha_enabled' => true,
        'captcha.default_captcha_driver' => 'geetest_v4',
        'captcha_geetest_v4.status' => false,
        'captcha_geetest_v4.id' => 'public-id',
        'captcha_geetest_v4.key' => 'private-key',
    ]);
    $assert($session->humanVerificationEnabled(), 'site policy must remain enabled when driver is unavailable');
    $assert(Captcha::isReady('captcha') === false, 'disabled driver must not report ready');
    $assert(!$session->verifyHumanVerification([]), 'registration must fail closed with disabled driver');
    $assert(!Captcha::verify('captcha', ['lot_number' => 'forged']), 'card middleware must fail closed with disabled driver');

    Config::$values['captcha_geetest_v4.status'] = true;
    Config::$values['captcha_geetest_v4.key'] = '';
    $assert(!Captcha::isReady('captcha'), 'missing private key must not report ready');
    $assert(!$session->verifyHumanVerification([]), 'registration must fail closed with missing private key');
    Config::$values['captcha_geetest_v4.key'] = 'private-key';
    $assert(Captcha::isReady('captcha'), 'complete active driver must report configured');
    $assert(!$session->verifyHumanVerification(['lot_number' => 'only-one-field']), 'incomplete GeeTest result must fail before remote verification');

    $settings(['captcha_enabled' => true, 'code_enabled' => true, 'default_captcha_driver' => 'gd_image', 'default_code_driver' => 'smtp_code'], [
        'captcha.captcha_enabled' => true,
        'captcha.default_captcha_driver' => 'gd_image',
        'captcha.code_enabled' => true,
        'user.captcha' => true,
    ]);
    $assert(ChannelManager::defaultDriver('captcha') === 'gd_image', 'configured image driver must be selected');
    CacheManager::$values['Captcha_image_test-image'] = 'ABCD';
    $assert(!Captcha::verify('captcha', ['image_id' => 'test-image', 'code' => []]), 'image driver must reject non-string input without a server error');
    $assert($session->verifyHumanVerification(['captcha_id' => 'test-image', 'captcha_code' => 'abcd']), 'image result must use its own request fields');
    $assert(!$session->verifyHumanVerification(['captcha_id' => 'test-image', 'captcha_code' => 'abcd']), 'image result must reject replay');

    $codeKey = 'verification-code:' . hash('sha256', 'Auth:test@example.com');
    CacheManager::$values[$codeKey] = ['hash' => password_hash('123456', PASSWORD_DEFAULT), 'expires_at' => time() + 300, 'attempts' => 0];
    $assert($session->registrationPolicy()->requiresCode('email'), 'registration email policy must remain independent');
    $assert($session->verifyCaptcha('test@example.com', '123456'), 'registration code must use Auth scene');
    $assert(!$session->verifyCaptcha('test@example.com', '123456'), 'registration code must reject replay');
    $state = ['hash' => password_hash('123456', PASSWORD_DEFAULT), 'expires_at' => time() + 300, 'attempts' => 0];
    CacheManager::$values[$codeKey] = $state;
    for ($i = 0; $i < 5; $i++) $assert(!$session->verifyCaptcha('test@example.com', '000000'), 'invalid code must fail');
    $assert(!$session->verifyCaptcha('test@example.com', '123456'), 'exhausted code must fail even with the right secret');
    $assert((new RegistrationPolicy(['captcha' => true]))->accountTypes() === ['email'], 'legacy email policy must stay email-only');
    $assert((new RegistrationPolicy(['captcha' => false]))->accountTypes() === ['email', 'phone'], 'legacy open registration must drop usernames');
    $policy = new RegistrationPolicy(['registration_mode' => 'email_or_phone', 'captcha' => true, 'registration_sms_verification' => false]);
    $assert($policy->requiresCode('email') && !$policy->requiresCode('phone'), 'email and SMS verification switches are independent');
    $assert($session->registrationContact(' 13800000000 ', $policy) === ['13800000000', 'phone'], 'phone input resolves to the phone method');
    foreach (['old-username', '13800000000'] as $account) {
        try {
            $session->registrationContact($account, new RegistrationPolicy(['registration_mode' => 'email']));
            throw new \RuntimeException('Only allowed contact types can register');
        } catch (\app\api\ApiException) {
        }
    }

    fwrite(STDOUT, "PASS Human verification fail closed and captcha paths\n");
}
