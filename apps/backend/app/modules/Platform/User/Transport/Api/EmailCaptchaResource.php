<?php
declare(strict_types=1);

namespace app\modules\Platform\User\Transport\Api;

use app\shared\Transport\Http\Resource;

final class EmailCaptchaResource extends Resource
{
    public static function make($value): array
    {
        $data = self::normalize($value);
        return ['sent' => (bool) ($data['sent'] ?? false), 'expires_in' => (int) ($data['expires_in'] ?? 0)];
    }

    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['sent', 'expires_in'],
            'properties' => [
                'sent' => ['type' => 'boolean'],
                'expires_in' => ['type' => 'integer', 'minimum' => 1],
            ],
        ];
    }
}
