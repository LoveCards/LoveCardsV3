<?php
namespace app\modules\Content\Tags\Transport\Api;

use app\shared\Transport\Http\Resource;

final class TagResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['id', 'aid', 'user_id', 'name', 'status', 'created_at', 'updated_at'],
            'properties' => [
                'id' => ['type' => 'integer'],
                'aid' => ['type' => 'integer'],
                'user_id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'status' => ['type' => 'integer'],
                'created_at' => ['type' => 'string', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'nullable' => true],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : (array) $data;

        return [
            'id' => (int) ($data['id'] ?? 0),
            'aid' => (int) ($data['aid'] ?? 0),
            'user_id' => (int) ($data['user_id'] ?? 0),
            'name' => (string) ($data['name'] ?? ''),
            'status' => (int) ($data['status'] ?? 0),
            'created_at' => $data['created_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
        ];
    }
}
