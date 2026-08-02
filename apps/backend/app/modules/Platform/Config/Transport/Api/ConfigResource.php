<?php

namespace app\modules\Platform\Config\Transport\Api;

use app\shared\Transport\Http\Resource;

final class ConfigResource extends Resource
{
    public static function make($value): array
    {
        $value = self::normalize($value);
        return is_array($value) ? $value : ['value' => $value];
    }
}
