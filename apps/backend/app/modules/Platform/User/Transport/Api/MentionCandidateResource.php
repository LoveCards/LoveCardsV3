<?php

declare(strict_types=1);

namespace app\modules\Platform\User\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 面向普通用户的最小提及候选结构，不暴露 ID、状态或隐私字段。 */
final class MentionCandidateResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['uid', 'username', 'avatar'],
            'properties' => [
                'uid' => ['type' => 'string', 'pattern' => '^[0-9]{12}$'],
                'username' => ['type' => 'string'],
                'avatar' => ['type' => 'string', 'nullable' => true],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = is_object($value) && method_exists($value, 'toArray')
            ? $value->toArray()
            : (array) $value;
        $avatar = isset($data['avatar']) && trim((string) $data['avatar']) !== ''
            ? trim((string) $data['avatar'])
            : null;

        return [
            'uid' => (string) ($data['uid'] ?? ''),
            'username' => trim((string) ($data['username'] ?? '')),
            'avatar' => $avatar,
        ];
    }
}
