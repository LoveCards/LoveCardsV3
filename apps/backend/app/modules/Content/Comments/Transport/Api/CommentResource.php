<?php
namespace app\modules\Content\Comments\Transport\Api;

use app\shared\Transport\Http\Resource;

/** Stable public comment contract. Comments are always scoped to a card. */
final class CommentResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['id', 'card_id', 'content', 'user_id', 'status'],
            'properties' => [
                'id' => ['type' => 'integer'], 'card_id' => ['type' => 'integer'],
                'parent_id' => ['type' => 'integer', 'nullable' => true],
                'user_id' => ['type' => 'integer'], 'content' => ['type' => 'string'],
                'status' => ['type' => 'integer'], 'created_at' => ['type' => 'string'],
                'updated_at' => ['type' => 'string', 'nullable' => true],
                'deleted' => ['type' => 'boolean'],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : (array) $data;
        return [
            'id' => (int) ($data['id'] ?? 0),
            'card_id' => (int) ($data['card_id'] ?? $data['pid'] ?? 0),
            'parent_id' => isset($data['parent_id']) && (int) $data['parent_id'] > 0 ? (int) $data['parent_id'] : null,
            'user_id' => (int) ($data['user_id'] ?? 0),
            'content' => (string) ($data['content'] ?? ''),
            'status' => (int) ($data['status'] ?? 0),
            'created_at' => (string) ($data['created_at'] ?? ''),
            'updated_at' => isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            'deleted' => (bool) ($data['deleted'] ?? false),
        ];
    }
}
