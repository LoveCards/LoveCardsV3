<?php

namespace app\modules\Content\Likes\Transport\Api;

use app\shared\Transport\Http\Resource;

final class LikeResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'properties' => [
            'id' => ['type' => 'integer'], 'aid' => ['type' => 'integer'], 'pid' => ['type' => 'integer'],
            'ref_type' => ['type' => 'string'], 'ref_id' => ['type' => 'integer'], 'uid' => ['type' => 'integer'],
            'created_at' => ['type' => 'string'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $like = is_array($data) ? $data : (array) $data;

        foreach (['ip', 'deleted_at'] as $field) {
            unset($like[$field]);
        }

        // 旧响应字段是 ref_id 的投影；升级前后均不依赖旧数据库列的值。
        if (isset($like['ref_id'])) {
            $like['aid'] = 1;
            $like['pid'] = (int) $like['ref_id'];
        }

        $fields = ['id', 'aid', 'pid', 'ref_type', 'ref_id', 'uid', 'created_at'];
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $like)) {
                $result[$field] = $like[$field];
            }
        }

        return $result;
    }
}
