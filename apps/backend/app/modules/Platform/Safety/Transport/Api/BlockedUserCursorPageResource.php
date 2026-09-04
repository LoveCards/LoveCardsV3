<?php
declare(strict_types=1);

namespace app\modules\Platform\Safety\Transport\Api;

use app\shared\Transport\Http\Resource;

final class BlockedUserCursorPageResource extends Resource
{
    public static function make($value): array
    {
        $page = self::normalize($value);
        return [
            'data' => array_values(array_map(static fn(array $item): array => [
                'user' => [
                    'uid' => (string) ($item['user']['uid'] ?? ''),
                    'username' => (string) $item['user']['username'],
                    'avatar' => $item['user']['avatar'] === null ? null : (string) $item['user']['avatar'],
                ],
                'blocked_at' => (string) $item['blocked_at'],
            ], (array) ($page['data'] ?? []))),
            'next_cursor' => isset($page['next_cursor']) ? (string) $page['next_cursor'] : null,
            'has_more' => (bool) ($page['has_more'] ?? false),
        ];
    }

    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['data', 'next_cursor', 'has_more'],
            'properties' => [
                'data' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'required' => ['user', 'blocked_at'],
                    'properties' => [
                        'user' => [
                            'type' => 'object', 'additionalProperties' => false,
                            'required' => ['uid', 'username', 'avatar'],
                            'properties' => [
                                'uid' => ['type' => 'string', 'pattern' => '^[0-9]{12}$'],
                                'username' => ['type' => 'string'],
                                'avatar' => ['type' => 'string', 'nullable' => true],
                            ],
                        ],
                        'blocked_at' => ['type' => 'string'],
                    ],
                ]],
                'next_cursor' => ['type' => 'string', 'nullable' => true],
                'has_more' => ['type' => 'boolean'],
            ],
        ];
    }
}
