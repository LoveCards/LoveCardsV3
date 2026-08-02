<?php

namespace app\modules\Operations\Version\Transport\Api;

use app\shared\Transport\Http\Resource;

final class SystemResource extends Resource
{
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
