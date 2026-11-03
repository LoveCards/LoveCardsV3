<?php
declare(strict_types=1);

namespace app\modules\Platform\Auth\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AdminSessionResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'id' => ['type' => 'integer'], 'user_id' => ['type' => 'integer'], 'token_expires_at' => ['type' => 'string'],
            'renewable_until' => ['type' => 'string'], 'security_version' => ['type' => 'integer'],
            'revoked_at' => ['type' => 'string', 'nullable' => true], 'revoked_reason' => ['type' => 'string', 'nullable' => true],
            'rotated_to_session_id' => ['type' => 'integer', 'nullable' => true], 'created_at' => ['type' => 'string'],
            'active' => ['type' => 'boolean'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : [];
        unset($data['jti_hash']);
        return $data;
    }
}
