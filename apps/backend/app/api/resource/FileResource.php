<?php

namespace app\api\resource;

final class FileResource extends Resource
{
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
