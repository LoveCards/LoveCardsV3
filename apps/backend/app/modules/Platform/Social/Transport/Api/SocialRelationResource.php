<?php
declare(strict_types=1);

namespace app\modules\Platform\Social\Transport\Api;

use app\shared\Transport\Http\Resource;

final class SocialRelationResource extends Resource
{
    public static function make($value): array
    {
        $data = self::normalize($value);
        return [
            'following' => (bool) ($data['following'] ?? false),
            'followed_by' => (bool) ($data['followed_by'] ?? false),
            'blocked_by_me' => (bool) ($data['blocked_by_me'] ?? false),
            'counts' => [
                'followers' => (int) ($data['counts']['followers'] ?? 0),
                'following' => (int) ($data['counts']['following'] ?? 0),
            ],
        ];
    }

    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['following','followed_by','blocked_by_me','counts'],
            'properties' => [
                'following' => ['type' => 'boolean'], 'followed_by' => ['type' => 'boolean'], 'blocked_by_me' => ['type' => 'boolean'],
                'counts' => [
                    'type' => 'object', 'additionalProperties' => false, 'required' => ['followers','following'],
                    'properties' => ['followers' => ['type' => 'integer', 'minimum' => 0], 'following' => ['type' => 'integer', 'minimum' => 0]],
                ],
            ],
        ];
    }
}
