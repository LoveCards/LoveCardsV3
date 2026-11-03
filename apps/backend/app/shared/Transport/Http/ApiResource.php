<?php

namespace app\shared\Transport\Http;

final class ApiResource extends Resource
{
    public static function make($value): array
    {
        $value = self::normalize($value);
        return is_array($value) ? $value : ['value' => $value];
    }
}
