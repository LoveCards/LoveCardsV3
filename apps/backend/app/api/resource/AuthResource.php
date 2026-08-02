<?php

namespace app\api\resource;

/** Public response contract for login/register/guest. */
final class AuthResource extends Resource
{
    public static function make($value): array
    {
        $data = is_object($value) && method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        return array_key_exists('token', $data) ? ['token' => (string) $data['token']] : [];
    }
}
