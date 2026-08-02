<?php

namespace app\modules\Operations\Dashboard\Transport\Api;

use app\shared\Transport\Http\Resource;

final class DashboardResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'properties' => [
            'count' => ['type' => 'object', 'additionalProperties' => true],
            'chart' => ['type' => 'object', 'additionalProperties' => true],
            'ver' => ['type' => 'string'], 'notice' => ['type' => 'array'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $dashboard = is_array($data) ? $data : (array) $data;

        $fields = ['count', 'chart', 'ver', 'notice'];
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $dashboard)) {
                $result[$field] = $dashboard[$field];
            }
        }

        return $result;
    }
}
