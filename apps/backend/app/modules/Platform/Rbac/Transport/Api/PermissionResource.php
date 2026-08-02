<?php

namespace app\modules\Platform\Rbac\Transport\Api;

use app\shared\Transport\Http\Resource;

final class PermissionResource extends Resource
{
    public static function make($value): array
    {
        $data = self::normalize($value);
        $permission = is_array($data) ? $data : (array) $data;

        return [
            'capability' => (string) ($permission['capability'] ?? ''),
            'description' => (string) ($permission['description'] ?? ''),
        ];
    }
}
