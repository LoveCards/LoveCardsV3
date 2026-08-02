<?php

namespace app\api\resource;

final class ConfigResource extends Resource
{
    public static function make($value): array
    {
        $value = self::normalize($value);
        return is_array($value) ? $value : ['value' => $value];
    }
}
