<?php

namespace app\modules\Platform\Captcha\Infrastructure\Driver;

use app\modules\Platform\Config\Public\Config as ConfigService;

class GeetestDriver extends AbstractDriver
{
    public static function type(): string
    {
        return 'captcha';
    }

    public static function meta(): array
    {
        return [
            'slug' => 'geetest_v4',
            'type' => 'captcha',
            'name' => '极验验证 v4',
            'icon' => 'mdi-shield-check',
            'fields' => [
                ['key' => 'status', 'label' => '启用状态', 'type' => 'checkbox'],
                ['key' => 'id', 'label' => 'Captcha ID', 'type' => 'text'],
                ['key' => 'key', 'label' => 'Captcha Key', 'type' => 'password'],
            ],
        ];
    }

    public static function configured(): bool
    {
        return (bool) ConfigService::get('captcha_geetest_v4.status', false)
            && trim((string) ConfigService::get('captcha_geetest_v4.id', '')) !== ''
            && trim((string) ConfigService::get('captcha_geetest_v4.key', '')) !== '';
    }

    public function generate(array $params): array
    {
        return [
            'captcha_id' => ConfigService::get('captcha_geetest_v4.id', ''),
            'status' => self::configured(),
            'driver' => 'geetest_v4',
        ];
    }

    public function verify(array $params): bool
    {
        if (!self::configured()) return false;

        $lotNumber = (string) ($params['lot_number'] ?? '');
        $captchaOutput = (string) ($params['captcha_output'] ?? '');
        $passToken = (string) ($params['pass_token'] ?? '');
        $genTime = (string) ($params['gen_time'] ?? '');
        if ($lotNumber === '' || $captchaOutput === '' || $passToken === '' || $genTime === '') return false;

        $captchaId = (string) ConfigService::get('captcha_geetest_v4.id', '');
        $captchaKey = (string) ConfigService::get('captcha_geetest_v4.key', '');
        $body = http_build_query([
            'lot_number' => $lotNumber,
            'captcha_output' => $captchaOutput,
            'pass_token' => $passToken,
            'gen_time' => $genTime,
            'sign_token' => hash_hmac('sha256', $lotNumber, $captchaKey),
        ]);
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $body,
            'timeout' => 5,
        ]]);
        $url = 'https://gcaptcha4.geetest.com/validate?captcha_id=' . rawurlencode($captchaId);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) return false;

        $result = json_decode($response, true);
        return is_array($result)
            && ($result['status'] ?? null) === 'success'
            && ($result['result'] ?? null) === 'success';
    }
}
