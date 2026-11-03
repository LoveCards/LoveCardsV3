<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AdminMigrationResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => true, 'properties' => [
            'migration' => ['type' => 'object'], 'state' => ['type' => 'string'], 'progress' => ['type' => 'object', 'nullable' => true],
            'id' => ['type' => 'string'], 'phase' => ['type' => 'string'], 'status' => ['type' => 'string'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        return is_array($data) ? $data : [];
    }
}
