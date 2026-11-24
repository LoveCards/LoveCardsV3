<?php

declare(strict_types=1);

namespace app\modules\Platform\Library\Transport\Api;

use app\shared\Transport\Http\Resource;

final class SavedCardIdsResource extends Resource
{
    public static function make($value): array
    {
        return ['saved_ids' => array_values($value['saved_ids'])];
    }

    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'properties' => ['saved_ids' => ['type' => 'array', 'items' => ['type' => 'integer']]],
            'required' => ['saved_ids'],
            'additionalProperties' => false,
        ];
    }
}
