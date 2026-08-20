<?php

namespace app\modules\Platform\Auth\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AuthPolicyResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'registration' => ['type' => 'object'],
                'guest' => ['type' => 'object'],
                'human_verification' => ['type' => 'object'],
            ],
            'required' => ['registration', 'guest', 'human_verification'],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);

        return [
            'registration' => (array) ($data['registration'] ?? []),
            'guest' => (array) ($data['guest'] ?? []),
            'human_verification' => (array) ($data['human_verification'] ?? []),
        ];
    }
}
