<?php

namespace app\modules\Platform\Rbac\Transport\Api;

use app\shared\Transport\Http\Resource;

final class PermissionResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'properties' => [
            'capability' => ['type' => 'string'], 'scope' => ['type' => 'string', 'enum' => ['own', 'all', 'global']],
            'description' => ['type' => 'string'],
        ], 'required' => ['capability', 'scope', 'description']];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $permission = is_array($data) ? $data : (array) $data;

        return [
            'capability' => (string) ($permission['capability'] ?? ''),
            'scope' => (string) ($permission['scope'] ?? ''),
            'description' => (string) ($permission['description'] ?? ''),
        ];
    }
}
