<?php

namespace app\modules\Platform\Auth\Transport\Api;

use app\shared\Transport\Http\Resource;

/** Public response contract for login/register/guest. */
final class AuthResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'properties' => ['token' => ['type' => 'string']], 'required' => ['token']];
    }

    public static function make($value): array
    {
        $data = is_object($value) && method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        return array_key_exists('token', $data) ? ['token' => (string) $data['token']] : [];
    }
}
