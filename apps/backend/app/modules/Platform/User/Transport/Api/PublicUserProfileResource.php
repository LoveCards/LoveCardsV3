<?php
declare(strict_types=1);

namespace app\modules\Platform\User\Transport\Api;

use app\shared\Transport\Http\Resource;

final class PublicUserProfileResource extends Resource
{
    public static function make($value): array
    {
        $data = self::normalize($value);
        return [
            'uid' => (string) ($data['uid'] ?? ''),
            'username' => (string) ($data['username'] ?? ''),
            'avatar' => self::nullable($data['avatar'] ?? null),
            'cover_url' => self::nullable($data['cover_url'] ?? null),
            'bio' => self::nullable($data['bio'] ?? null),
            'location' => self::nullable($data['location'] ?? null),
            'gender' => self::nullable($data['gender'] ?? null),
            'birth_date' => self::nullable($data['birth_date'] ?? null),
            'followers' => (int) ($data['followers'] ?? 0),
            'following' => (int) ($data['following'] ?? 0),
            'can_view_relations' => (bool) ($data['can_view_relations'] ?? false),
            'stats' => [
                'received_likes' => max(0, (int) ($data['stats']['received_likes'] ?? 0)),
            ],
        ];
    }

    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['uid', 'username', 'avatar', 'cover_url', 'bio', 'location', 'gender', 'birth_date', 'followers', 'following', 'can_view_relations', 'stats'],
            'properties' => [
                'uid' => ['type' => 'string', 'pattern' => '^[0-9]{12}$'],
                'username' => ['type' => 'string'],
                'avatar' => ['type' => 'string', 'nullable' => true],
                'cover_url' => ['type' => 'string', 'nullable' => true],
                'bio' => ['type' => 'string', 'nullable' => true],
                'location' => ['type' => 'string', 'nullable' => true],
                'gender' => ['type' => 'string', 'nullable' => true],
                'birth_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                'followers' => ['type' => 'integer', 'minimum' => 0],
                'following' => ['type' => 'integer', 'minimum' => 0],
                'can_view_relations' => ['type' => 'boolean'],
                'stats' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['received_likes'],
                    'properties' => [
                        'received_likes' => ['type' => 'integer', 'minimum' => 0],
                    ],
                ],
            ],
        ];
    }

    private static function nullable(mixed $value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
