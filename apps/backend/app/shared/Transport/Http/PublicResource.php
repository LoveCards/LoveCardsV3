<?php
namespace app\shared\Transport\Http;

/** Conservative resource for legacy payloads while modules adopt explicit schemas. */
abstract class PublicResource extends Resource
{
    protected static array $hidden = ['password', 'password_hash', 'post_ip', 'deleted_at'];
    public static function make($value): array
    {
        $data = is_object($value) && method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        foreach (static::$hidden as $field) unset($data[$field]);
        return $data;
    }
}
