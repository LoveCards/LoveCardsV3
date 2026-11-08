<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AdminRuntimeResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'facilities' => ['type' => 'array'], 'workers' => ['type' => 'array'], 'eventing' => ['type' => 'object'],
            'communication' => ['type' => 'object'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        if (!is_array($data)) return [];
        $data['facilities'] = array_values(is_array($data['facilities'] ?? null) ? $data['facilities'] : []);
        $data['workers'] = array_values(is_array($data['workers'] ?? null) ? $data['workers'] : []);
        return $data;
    }
}
