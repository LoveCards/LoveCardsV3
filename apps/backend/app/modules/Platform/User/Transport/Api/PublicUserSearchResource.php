<?php

declare(strict_types=1);

namespace app\modules\Platform\User\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 搜索结果只输出可公开展示的主页身份字段。 */
final class PublicUserSearchResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['uid', 'username', 'avatar', 'bio'],
            'properties' => [
                'uid' => ['type' => 'string', 'pattern' => '^[0-9]{12}$'],
                'username' => ['type' => 'string'],
                'avatar' => ['type' => 'string', 'nullable' => true],
                'bio' => ['type' => 'string', 'nullable' => true],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = is_object($value) && method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        $avatar = trim((string) ($data['avatar'] ?? ''));
        $bio = trim((string) ($data['bio'] ?? ''));
        return [
            'uid' => (string) ($data['uid'] ?? ''),
            'username' => trim((string) ($data['username'] ?? '')),
            'avatar' => $avatar === '' ? null : $avatar,
            'bio' => $bio === '' ? null : $bio,
        ];
    }
}
