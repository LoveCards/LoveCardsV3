<?php

namespace app\api\resource;

final class DashboardResource extends Resource
{
    public static function make($value): array
    {
        $data = self::normalize($value);
        $dashboard = is_array($data) ? $data : (array) $data;

        $fields = ['count', 'chart', 'ver', 'notice'];
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $dashboard)) {
                $result[$field] = $dashboard[$field];
            }
        }

        return $result;
    }
}
