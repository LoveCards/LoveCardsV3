<?php

namespace app\modules\Content\Tags\Transport\Api;

use app\shared\Transport\Http\Resource;

final class TagsPolicyResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['allow_user_create', 'review_new_tags'],
            'properties' => [
                'allow_user_create' => ['type' => 'boolean'],
                'review_new_tags'   => ['type' => 'boolean'],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = is_array($value) ? $value : (array) $value;

        return [
            'allow_user_create' => (bool) ($data['allow_user_create'] ?? true),
            'review_new_tags'   => (bool) ($data['review_new_tags'] ?? false),
        ];
    }
}
