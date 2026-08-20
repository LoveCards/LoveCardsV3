<?php

namespace app\modules\Platform\Captcha\Transport\Api;

use app\shared\Transport\Http\Resource;

final class CaptchaChallengeResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'driver' => ['type' => 'string'],
                'captcha_id' => ['type' => 'string', 'nullable' => true],
                'image_id' => ['type' => 'string', 'nullable' => true],
                'image_base64' => ['type' => 'string', 'nullable' => true],
                'status' => ['type' => 'boolean', 'nullable' => true],
            ],
            'required' => ['driver'],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : [];
        $fields = ['driver', 'captcha_id', 'image_id', 'image_base64', 'status'];
        $result = [];
        foreach ($fields as $field) if (array_key_exists($field, $data)) $result[$field] = $data[$field];
        return $result;
    }
}
