<?php

namespace app\modules\Content\Likes\Transport\Api;

use app\shared\Transport\Http\Resource;

final class LikeResource extends Resource
{
    public static function make($value): array
    {
        $data = self::normalize($value);
        $like = is_array($data) ? $data : (array) $data;

        foreach (['ip', 'deleted_at'] as $field) {
            unset($like[$field]);
        }

        $fields = ['id', 'aid', 'pid', 'uid', 'created_at', 'updated_at'];
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $like)) {
                $result[$field] = $like[$field];
            }
        }

        return $result;
    }
}
