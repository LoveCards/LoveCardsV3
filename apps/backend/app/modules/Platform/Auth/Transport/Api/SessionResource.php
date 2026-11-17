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
            $roleIds = array_map('intval', (array) ($value['roles'] ?? []));
            return [
                'uid' => self::publicUid($value['uid'] ?? null),
                'visitor' => self::isVisitorSession((bool) ($value['visitor'] ?? false), $roleIds),
                'roles' => $roleIds,
                'capabilities' => array_values((array) ($value['capabilities'] ?? [])),
                'grants' => array_values((array) ($value['grants'] ?? [])),
            ];
        }

        $roleIds = array_map('intval', $context->roleIds());
        return [
            'uid' => self::publicUid($context->uid()),
            'visitor' => self::isVisitorSession($context->isVisitor(), $roleIds),
            'roles' => $roleIds,
            'capabilities' => array_values($context->capabilities()),
            'grants' => array_values($context->grants()->toArray()),
        ];
    }

    private static function isVisitorSession(bool $anonymousVisitor, array $roleIds): bool
    {
        // AuthContext 的访客标记用于无 Token 权限边界；会话响应也要识别持 Token 的游客账号。
        return $anonymousVisitor || in_array((int) config('system.system_roles.guest'), $roleIds, true);
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
