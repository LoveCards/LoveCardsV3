<?php

namespace app\shared\Transport\Http;

/** 持续增长列表的稳定游标响应结构。 */
final class CursorPage
{
    /**
     * @param array<int, mixed> $items
     * @return array{data: array<int, mixed>, next_cursor: ?string, has_more: bool}
     */
    public static function make(array $items, ?string $nextCursor, bool $hasMore): array
    {
        return [
            'data' => array_values($items),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /** @return array<string, mixed> */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['data', 'next_cursor', 'has_more'],
            'properties' => [
                'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                'next_cursor' => ['type' => ['string', 'null']],
                'has_more' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ];
    }
}
