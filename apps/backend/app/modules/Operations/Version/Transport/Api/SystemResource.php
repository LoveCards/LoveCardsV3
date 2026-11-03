<?php

namespace app\modules\Operations\Version\Transport\Api;

use app\shared\Transport\Http\Resource;

final class SystemResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'properties' => [
            'ver' => ['type' => 'object', 'properties' => [
                'app_name' => ['type' => 'string'],
                'homepage' => ['type' => 'string'],
                'version' => ['type' => 'string'],
                'build' => ['type' => 'integer'],
                'github' => ['type' => 'string'],
                'qgroup' => ['type' => 'string'],
            ]],
            'latest' => ['type' => 'object'],
            'verlog' => ['type' => 'string'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $system = is_array($data) ? $data : (array) $data;

        $fields = ['ver', 'latest', 'verlog'];
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $system)) {
                $result[$field] = $system[$field];
            }
        }

        return $result;
    }
}
