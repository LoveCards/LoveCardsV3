<?php
declare(strict_types=1);

namespace app\modules\Platform\Auth\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AdminSecurityResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'user' => ['type' => 'object'], 'privacy' => ['type' => 'object', 'nullable' => true],
            'sessions' => ['type' => 'object'], 'recovery_challenges' => ['type' => 'integer'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        return is_array($data) ? $data : [];
    }
}
