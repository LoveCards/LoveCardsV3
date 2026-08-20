<?php

namespace app\modules\Platform\Auth\Transport\Api;

use app\shared\Transport\Http\Resource;

final class SessionResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uid' => ['type' => 'integer'],
                'visitor' => ['type' => 'boolean'],
                'roles' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'capabilities' => ['type' => 'array', 'items' => ['type' => 'string']],
                'grants' => ['type' => 'array'],
            ],
            'required' => ['uid', 'visitor', 'roles', 'capabilities', 'grants'],
        ];
    }

    public static function make($value): array
    {
        $context = $value;
        if (is_array($value)) {
            return [
                'uid' => (int) ($value['uid'] ?? 0),
                'visitor' => (bool) ($value['visitor'] ?? false),
                'roles' => array_map('intval', (array) ($value['roles'] ?? [])),
                'capabilities' => array_values((array) ($value['capabilities'] ?? [])),
                'grants' => array_values((array) ($value['grants'] ?? [])),
            ];
        }

        return [
            'uid' => (int) $context->uid(),
            'visitor' => (bool) $context->isVisitor(),
            'roles' => array_map('intval', $context->roleIds()),
            'capabilities' => array_values($context->capabilities()),
            'grants' => array_values($context->grants()->toArray()),
        ];
    }
}
