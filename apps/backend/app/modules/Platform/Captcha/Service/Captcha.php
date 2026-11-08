<?php

namespace app\modules\Platform\Captcha\Service;

use app\modules\Platform\Captcha\Infrastructure\CaptchaFactory;
use app\modules\Platform\Captcha\Contract\CaptchaInterface;
use app\modules\Platform\Captcha\Infrastructure\Driver\GeetestDriver;

class Captcha
{
    public static function generate(string $type, array $params = [], ?string $driverSlug = null): array
    {
        if (!self::isEnabled($type)) {
            return ['status' => false, 'msg' => "{$type} 验证已关闭"];
        }

        $driver = self::resolve($type, $driverSlug);
        return $driver->generate($params);
    }

    public static function verify(string $type, array $params, ?string $driverSlug = null): bool
    {
        if (!self::isEnabled($type)) {
            return true;
        }

        $driver = self::resolve($type, $driverSlug);
        return $driver->verify($params);
    }

    /** @return 'valid'|'expired'|'invalid' */
    public static function verifyDetailed(string $type, array $params, ?string $driverSlug = null): string
    {
        if (!self::isEnabled($type)) return 'expired';
        $driver = self::resolve($type, $driverSlug);
        if (method_exists($driver, 'verifyDetailed')) return $driver->verifyDetailed($params);
        return $driver->verify($params) ? 'valid' : 'invalid';
    }

    public static function driver(string $type, ?string $driverSlug = null): CaptchaInterface
    {
        return self::resolve($type, $driverSlug);
    }

    public static function isEnabled(string $type): bool
    {
        return ChannelManager::isEnabled($type);
    }

    public static function isReady(string $type): bool
    {
        if ($type !== 'captcha') return false;
        return match (ChannelManager::defaultDriver($type)) {
            'geetest_v4' => GeetestDriver::configured(),
            'gd_image' => function_exists('imagecreatetruecolor'),
            default => false,
        };
    }

    public static function delete(string $type, string $key, string $scene = 'default', ?string $driverSlug = null): bool
    {
        return self::resolve($type, $driverSlug)->delete($key, $scene);
    }

    private static function resolve(string $type, ?string $driverSlug): CaptchaInterface
    {
        if ($driverSlug) {
            return CaptchaFactory::make($driverSlug);
        }

        $defaultSlug = ChannelManager::defaultDriver($type);
        return CaptchaFactory::make($defaultSlug);
    }
}
