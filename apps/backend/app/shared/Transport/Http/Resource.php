<?php

namespace app\shared\Transport\Http;

/** API response data contract. */
abstract class Resource
{
    abstract public static function make($value): array;

    /** Machine-readable response contract; dynamic resources override this. */
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => true,
            'x-runtime-schema' => true,
        ];
    }

    public static function collection(array $items): array
    {
        return array_map(static fn ($item): array => static::make($item), $items);
    }

    public static function paginate(array $result): array
    {
        if (!isset($result['data']) || !is_array($result['data'])) {
            return $result;
        }
        $result['data'] = static::collection($result['data']);
        return $result;
    }

    public static function normalize($value)
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }
        if (!is_array($value)) {
            return $value;
        }
        return array_map(static fn ($item) => self::normalize($item), $value);
    }
}
