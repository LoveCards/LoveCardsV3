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
            'session_mutation_allowed' => ['type' => 'boolean'],
        ], 'required' => ['user', 'privacy', 'sessions', 'recovery_challenges', 'session_mutation_allowed']];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        if (!is_array($data)) return [];
        $data['session_mutation_allowed'] = (bool) ($data['session_mutation_allowed'] ?? false);
        return $data;
    }
}
