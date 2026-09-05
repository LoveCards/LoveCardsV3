<?php

namespace app\modules\Content\Cards\Transport\Api;

use app\shared\Transport\Http\Resource;

/** Public response contract for a card. */
final class CardResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'], 'is_top' => ['type' => 'integer'], 'status' => ['type' => 'integer'], 'visibility' => ['type' => 'string', 'enum' => ['public', 'followers', 'private']],
                'user_id' => ['type' => 'integer'], 'uid' => ['type' => 'string', 'nullable' => true], 'username' => ['type' => 'string'],
                'data' => ['type' => 'object', 'additionalProperties' => true],
                'cover' => ['type' => 'string', 'nullable' => true], 'pictures' => ['type' => 'array', 'items' => ['type' => 'string']],
                'media' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'properties' => [
                        'file_id' => ['type' => 'integer', 'nullable' => true],
                        'type' => ['type' => 'string', 'enum' => ['image', 'video']],
                        'url' => ['type' => 'string'],
                        'mime_type' => ['type' => 'string', 'nullable' => true],
                        'size' => ['type' => 'integer', 'nullable' => true],
                        'width' => ['type' => 'integer', 'nullable' => true],
                        'height' => ['type' => 'integer', 'nullable' => true],
                        'duration_ms' => ['type' => 'integer', 'nullable' => true],
                        'poster_url' => ['type' => 'string', 'nullable' => true],
                        'is_animated' => ['type' => 'boolean'],
                        'position' => ['type' => 'integer'],
                    ],
                ]],
                'content' => ['type' => 'string'], 'tags' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'topics' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['id', 'name'], 'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']]]],
                'mentions' => self::mentionsSchema(),
                'goods' => ['type' => 'integer'], 'views' => ['type' => 'integer'], 'comments' => ['type' => 'integer'],
                'created_at' => ['type' => 'string'], 'updated_at' => ['type' => 'string'],
            ],
        ];
    }

    public static function make($value): array
    {
        $card = is_object($value) && method_exists($value, 'toArray')
            ? $value->toArray()
            : (array) $value;
        foreach (['data', 'tags', 'pictures', 'media'] as $field) {
            if (isset($card[$field]) && is_string($card[$field])) {
                $decoded = json_decode($card[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $card[$field] = $decoded;
                }
            }
        }
        $rawTags = $card['tags'] ?? [];
        $visibility = (string) ($card['visibility'] ?? 'public');
        $card['visibility'] = in_array($visibility, ['public', 'followers', 'private'], true) ? $visibility : 'public';
        $card['tags'] = is_array($rawTags)
            ? array_values(array_filter(array_map('intval', $rawTags), static fn(int $id): bool => $id > 0))
            : [];
        $card['topics'] = is_array($card['topics'] ?? null) ? array_values($card['topics']) : [];
        $card['mentions'] = is_array($card['mentions'] ?? null) ? array_values($card['mentions']) : [];
        $fields = [
            'id', 'is_top', 'status', 'visibility', 'user_id', 'uid', 'username', 'data', 'cover', 'pictures', 'media',
            'content', 'tags', 'topics', 'mentions', 'goods', 'views', 'comments', 'created_at', 'updated_at',
        ];
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $card)) {
                $result[$field] = $card[$field];
            }
        }
        return $result;
    }

    private static function mentionsSchema(): array
    {
        return ['type' => 'array', 'items' => [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['start', 'end', 'username', 'uid', 'state', 'action_url'],
            'properties' => [
                'start' => ['type' => 'integer'], 'end' => ['type' => 'integer'],
                'username' => ['type' => 'string'], 'uid' => ['type' => 'string', 'nullable' => true],
                'state' => ['type' => 'string', 'enum' => ['active', 'unavailable']],
                'action_url' => ['type' => 'string', 'nullable' => true],
            ],
        ]];
    }
}
