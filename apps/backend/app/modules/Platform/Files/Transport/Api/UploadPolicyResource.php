<?php

declare(strict_types=1);

namespace app\modules\Platform\Files\Transport\Api;

use app\shared\Transport\Http\Resource;

final class UploadPolicyResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['scenes', 'card_media_limit'], 'properties' => [
            'scenes' => ['type' => 'object', 'additionalProperties' => ['type' => 'object', 'properties' => [
                'available' => ['type' => 'boolean'],
                'extensions' => ['type' => 'array', 'items' => ['type' => 'string']],
                'mime_patterns' => ['type' => 'array', 'items' => ['type' => 'string']],
                'site_max_bytes' => ['type' => 'integer'],
                'storage_max_bytes' => ['type' => 'integer', 'nullable' => true],
                'max_bytes' => ['type' => 'integer'],
            ]]],
            'card_media_limit' => ['type' => 'integer'],
        ]];
    }

    public static function make($value): array
    {
        return ['scenes' => $value['scenes'], 'card_media_limit' => $value['card_media_limit']];
    }
}
