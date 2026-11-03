<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

final class TrustedReleaseKeys
{
    /** @return array<string, string> */
    public static function configured(): array
    {
        $json = config('lifecycle.trusted_release_keys', '{}');
        if (!is_string($json) || trim($json) === '') return [];
        try {
            $value = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('LC_RELEASE_TRUSTED_KEYS_JSON 不是有效 JSON', 0, $exception);
        }
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new \RuntimeException('LC_RELEASE_TRUSTED_KEYS_JSON 必须是 key ID 到公钥路径的对象');
        }
        foreach ($value as $keyId => $path) {
            if (!is_string($keyId) || !is_string($path)) throw new \RuntimeException('Release 公钥配置包含非法条目');
        }
        return $value;
    }
}
