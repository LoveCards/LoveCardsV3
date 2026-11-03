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
            'required' => ['id', 'card_id', 'content', 'user_id', 'status', 'is_pinned'],
            'properties' => [
                'id' => ['type' => 'integer'], 'card_id' => ['type' => 'integer'],
                'parent_id' => ['type' => 'integer', 'nullable' => true],
                'user_id' => ['type' => 'integer'], 'uid' => ['type' => 'string', 'nullable' => true], 'username' => ['type' => 'string'], 'content' => ['type' => 'string'],
                'status' => ['type' => 'integer'], 'goods' => ['type' => 'integer'], 'created_at' => ['type' => 'string'],
                'is_pinned' => ['type' => 'boolean'],
                'mentions' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'required' => ['start', 'end', 'username', 'uid', 'state', 'action_url'],
                    'properties' => [
                        'start' => ['type' => 'integer'], 'end' => ['type' => 'integer'],
                        'username' => ['type' => 'string'], 'uid' => ['type' => 'string', 'nullable' => true],
                        'state' => ['type' => 'string', 'enum' => ['active', 'unavailable']],
                        'action_url' => ['type' => 'string', 'nullable' => true],
                    ],
                ]],
                'updated_at' => ['type' => 'string', 'nullable' => true],
                'deleted' => ['type' => 'boolean'],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : (array) $data;
        $result = [
            'id' => (int) ($data['id'] ?? 0),
            'card_id' => (int) ($data['card_id'] ?? $data['pid'] ?? 0),
            'parent_id' => isset($data['parent_id']) && (int) $data['parent_id'] > 0 ? (int) $data['parent_id'] : null,
            'user_id' => (int) ($data['user_id'] ?? 0),
            'uid' => isset($data['uid']) ? (string) $data['uid'] : null,
            'content' => (string) ($data['content'] ?? ''),
            'status' => (int) ($data['status'] ?? 0),
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'mentions' => is_array($data['mentions'] ?? null) ? array_values($data['mentions']) : [],
            'goods' => (int) ($data['goods'] ?? 0),
            'created_at' => (string) ($data['created_at'] ?? ''),
            'updated_at' => isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            'deleted' => (bool) ($data['deleted'] ?? false),
        ];
        $username = trim((string) ($data['username'] ?? ''));
        if ($username !== '') $result['username'] = $username;
        return $result;
    }
}
