<?php

namespace app\modules\Content\Comments\Transport\Api;

use app\shared\Transport\Http\Resource;

/** Stable response contract for comment creation. */
final class CommentCreatedResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['id', 'status'],
            'properties' => [
                'id' => ['type' => 'integer'],
                'status' => ['type' => 'integer'],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : (array) $data;

        return [
            'id' => (int) ($data['id'] ?? 0),
            'status' => (int) ($data['status'] ?? 0),
        ];
    }
}
