<?php

namespace app\modules\Platform\Rbac\Transport\Api;

use app\shared\Transport\Http\Resource;

final class RoleResource extends Resource
{
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
