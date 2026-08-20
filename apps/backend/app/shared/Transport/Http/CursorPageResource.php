<?php
declare(strict_types=1);

namespace app\shared\Transport\Http;

/** Transport resource for cursor-paginated API responses. */
final class CursorPageResource extends Resource
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
            throw new \UnexpectedValueException('Cursor Page 响应结构不合法');
        }

        return [
            'data' => array_values($page['data']),
            'next_cursor' => $page['next_cursor'],
            'has_more' => $page['has_more'],
        ];
    }

    public static function schema(string $variant = 'default'): array
    {
        return CursorPage::schema();
    }
}
