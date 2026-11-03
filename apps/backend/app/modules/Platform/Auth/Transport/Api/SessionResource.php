<?php

namespace app\modules\Platform\Auth\Transport\Api;

use app\shared\Transport\Http\Resource;
use think\facade\Db;

final class SessionResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uid' => ['type' => 'string', 'pattern' => '^[0-9]{12}$'],
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
                'uid' => self::publicUid($value['uid'] ?? null),
                'visitor' => (bool) ($value['visitor'] ?? false),
                'roles' => array_map('intval', (array) ($value['roles'] ?? [])),
                'capabilities' => array_values((array) ($value['capabilities'] ?? [])),
                'grants' => array_values((array) ($value['grants'] ?? [])),
            ];
        }

        return [
            'uid' => self::publicUid($context->uid()),
            'visitor' => (bool) $context->isVisitor(),
            'roles' => array_map('intval', $context->roleIds()),
            'capabilities' => array_values($context->capabilities()),
            'grants' => array_values($context->grants()->toArray()),
        ];
    }

    private static function publicUid($value): string
    {
        $candidate = trim((string) ($value ?? ''));
        if (preg_match('/^[0-9]{12}$/', $candidate)) return $candidate;
        $internalId = (int) ($value ?? 0);
        if ($internalId <= 0) return '';
        return (string) (Db::table('users')->where('id', $internalId)->value('uid') ?? '');
    }
}
