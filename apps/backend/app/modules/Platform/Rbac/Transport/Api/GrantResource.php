<?php

namespace app\modules\Platform\Rbac\Transport\Api;

use app\shared\Transport\Http\Resource;

final class GrantResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'properties' => [
            'capability' => ['type' => 'string'],
            'scope' => ['type' => 'string', 'enum' => ['own', 'all', 'global']],
        ], 'required' => ['capability', 'scope']];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        return ['capability' => (string) ($data['capability'] ?? ''), 'scope' => (string) ($data['scope'] ?? '')];
    }
}
