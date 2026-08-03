<?php

namespace app\modules\Content\Likes\Transport\Api;

use app\shared\Transport\Http\Resource;

/** Stable response contract for a like command. */
final class LikeCountResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['likes'],
            'properties' => [
                'likes' => ['type' => 'integer'],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : (array) $data;

        return ['likes' => (int) ($data['likes'] ?? 0)];
    }
}
