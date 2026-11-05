<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 通信运行时快照（契约 §1.1）。effective_mode 永远是服务端派生值。 */
final class AdminRuntimeConfigResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'desired_mode' => ['type' => 'string'],
            'effective_mode' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'revision' => ['type' => 'integer'],
            'reason_code' => ['type' => ['string', 'null']],
            'checked_at' => ['type' => 'string'],
            'settings' => ['type' => 'object'],
            'last_apply_state' => ['type' => ['string', 'null']],
            'last_apply_reason' => ['type' => ['string', 'null']],
            'capabilities' => ['type' => 'array'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        if (!is_array($data)) return [];
        $data['capabilities'] = array_values(is_array($data['capabilities'] ?? null) ? $data['capabilities'] : []);
        return $data;
    }
}
