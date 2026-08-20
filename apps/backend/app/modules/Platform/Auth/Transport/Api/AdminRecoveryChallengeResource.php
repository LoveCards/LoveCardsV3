<?php
declare(strict_types=1);

namespace app\modules\Platform\Auth\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AdminRecoveryChallengeResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'id' => ['type' => 'integer'], 'user_id' => ['type' => 'integer', 'nullable' => true],
            'purpose' => ['type' => 'string'], 'expires_at' => ['type' => 'string'], 'consumed_at' => ['type' => 'string', 'nullable' => true],
            'attempts' => ['type' => 'integer'], 'created_at' => ['type' => 'string'], 'state' => ['type' => 'string'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : [];
        unset($data['secret_hash'], $data['subject_hash']);
        return $data;
    }
}
