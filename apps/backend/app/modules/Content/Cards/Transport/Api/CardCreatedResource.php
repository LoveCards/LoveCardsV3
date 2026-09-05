<?php

namespace app\modules\Content\Cards\Transport\Api;

use app\shared\Transport\Http\Resource;

/** Stable response contract for card creation. */
final class CardCreatedResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['id', 'status', 'visibility'],
            'properties' => [
                'id' => ['type' => 'integer'],
                'status' => ['type' => 'integer'],
                'visibility' => ['type' => 'string', 'enum' => ['public', 'followers', 'private']],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : (array) $data;

        $result = [
            'id' => (int) ($data['id'] ?? 0),
            'status' => (int) ($data['status'] ?? 0),
        ];
        if (array_key_exists('visibility', $data)) {
            $result['visibility'] = (string) $data['visibility'];
        }
        return $result;
    }
}
