<?php

namespace app\modules\Platform\Files\Transport\Api;

use app\shared\Transport\Http\Resource;

final class FileResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'properties' => [
            'id' => ['type' => 'integer'], 'hash' => ['type' => 'string'], 'channel_slug' => ['type' => 'string'],
            'user_id' => ['type' => 'integer'], 'is_public' => ['type' => 'integer'], 'scene' => ['type' => 'string'],
            'ref_type' => ['type' => 'string', 'nullable' => true], 'ref_id' => ['type' => 'integer', 'nullable' => true],
            'original_name' => ['type' => 'string'], 'file_path' => ['type' => 'string'], 'path' => ['type' => 'string'],
            'file_url' => ['type' => 'string'], 'url' => ['type' => 'string'], 'file_size' => ['type' => 'integer'],
            'size' => ['type' => 'integer'], 'file_ext' => ['type' => 'string'], 'mime_type' => ['type' => 'string'],
            'status' => ['type' => 'integer'], 'upload_status' => ['type' => 'integer'],
            'created_at' => ['type' => 'string'], 'updated_at' => ['type' => 'string'], 'expire_at' => ['type' => 'string', 'nullable' => true],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $file = is_array($data) ? $data : (array) $data;

        foreach (['driver_path', 'deleted_at'] as $field) {
            unset($file[$field]);
        }

        $fields = [
            'id', 'hash', 'channel_slug', 'user_id', 'is_public', 'scene',
            'ref_type', 'ref_id', 'original_name', 'file_path', 'path',
            'file_url', 'url', 'file_size', 'size', 'file_ext', 'mime_type',
            'status', 'upload_status', 'created_at', 'updated_at', 'expire_at',
        ];

        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $file)) {
                $result[$field] = $file[$field];
            }
        }

        return $result;
    }
}
