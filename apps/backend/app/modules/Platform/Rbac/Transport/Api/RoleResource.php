<?php

namespace app\modules\Platform\Rbac\Transport\Api;

use app\shared\Transport\Http\Resource;

final class RoleResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'properties' => [
            'id' => ['type' => 'integer'], 'name' => ['type' => 'string'], 'slug' => ['type' => 'string'],
            'description' => ['type' => 'string'], 'is_system' => ['type' => 'integer'], 'status' => ['type' => 'integer'],
            'created_at' => ['type' => 'string'], 'updated_at' => ['type' => 'string'], 'capabilities' => ['type' => 'array'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $role = is_array($data) ? $data : (array) $data;

        $fields = [
            'id', 'name', 'slug', 'description', 'is_system', 'status',
            'created_at', 'updated_at', 'capabilities',
        ];

        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $role)) {
                $result[$field] = $role[$field];
            }
        }

        return $result;
    }
}
