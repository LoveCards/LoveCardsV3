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
                'registration' => [
                    'type' => 'object',
                    'properties' => [
                        'enabled' => ['type' => 'boolean'],
                        'account_types' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['email', 'phone']]],
                        'email_verification_required' => ['type' => 'boolean'],
                        'email_verification_available' => ['type' => 'boolean'],
                        'sms_verification_required' => ['type' => 'boolean'],
                        'sms_verification_available' => ['type' => 'boolean'],
                        'resend_after' => ['type' => 'integer'],
                    ],
                    'required' => ['enabled', 'account_types', 'email_verification_required', 'email_verification_available', 'sms_verification_required', 'sms_verification_available', 'resend_after'],
                ],
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
