<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 带强类型通知项的游标分页响应。 */
final class NotificationCursorPageResource extends Resource
{
    public static function make($value): array
    {
        $page = self::normalize($value);
        if (!is_array($page)
            || !isset($page['data'])
            || !is_array($page['data'])
            || !array_key_exists('next_cursor', $page)
            || ($page['next_cursor'] !== null && !is_string($page['next_cursor']))
            || !array_key_exists('has_more', $page)
            || !is_bool($page['has_more'])) {
            throw new \UnexpectedValueException('通知 Cursor Page 响应结构不合法');
        }

        return [
            'data' => NotificationItemResource::collection(array_values($page['data'])),
            'next_cursor' => $page['next_cursor'],
            'has_more' => $page['has_more'],
        ];
    }

    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['data', 'next_cursor', 'has_more'],
            'properties' => [
                'data' => ['type' => 'array', 'items' => NotificationItemResource::schema()],
                'next_cursor' => ['type' => 'string', 'nullable' => true],
                'has_more' => ['type' => 'boolean'],
            ],
        ];
    }
}
