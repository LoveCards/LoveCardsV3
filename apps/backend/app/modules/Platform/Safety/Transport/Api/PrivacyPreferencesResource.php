<?php
declare(strict_types=1);

namespace app\modules\Platform\Safety\Transport\Api;

use app\shared\Transport\Http\Resource;

final class PrivacyPreferencesResource extends Resource
{
    public static function make($value): array
    {
        $data = self::normalize($value);
        return [
            'profile_visibility' => (string) ($data['profile_visibility'] ?? 'public'),
            'messages_from' => (string) ($data['messages_from'] ?? 'everyone'),
            'show_relations' => (bool) ($data['show_relations'] ?? true),
            'gender_visibility' => (string) ($data['gender_visibility'] ?? 'private'),
            'birth_date_visibility' => (string) ($data['birth_date_visibility'] ?? 'private'),
        ];
    }

    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['profile_visibility', 'messages_from', 'show_relations', 'gender_visibility', 'birth_date_visibility'],
            'properties' => [
                'profile_visibility' => ['type' => 'string', 'enum' => ['public', 'followers', 'private']],
                'messages_from' => ['type' => 'string', 'enum' => ['everyone', 'following', 'none']],
                'show_relations' => ['type' => 'boolean'],
                'gender_visibility' => ['type' => 'string', 'enum' => ['public', 'followers', 'private']],
                'birth_date_visibility' => ['type' => 'string', 'enum' => ['public', 'followers', 'private']],
            ],
        ];
    }
}
