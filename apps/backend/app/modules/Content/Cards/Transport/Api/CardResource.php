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
                'id' => ['type' => 'integer'], 'is_top' => ['type' => 'integer'], 'status' => ['type' => 'integer'],
                'user_id' => ['type' => 'integer'], 'data' => ['type' => 'object', 'additionalProperties' => true],
                'cover' => ['type' => 'string', 'nullable' => true], 'pictures' => ['type' => 'array'],
                'content' => ['type' => 'string'], 'tags' => ['type' => 'array'],
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
        foreach (['data', 'tags', 'pictures'] as $field) {
            if (isset($card[$field]) && is_string($card[$field])) {
                $decoded = json_decode($card[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $card[$field] = $decoded;
                }
            }
        }
        $fields = [
            'id', 'is_top', 'status', 'user_id', 'data', 'cover', 'pictures',
            'content', 'tags', 'goods', 'views', 'comments', 'created_at', 'updated_at',
        ];
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $card)) {
                $result[$field] = $card[$field];
            }
        }
        return $result;
    }
}
