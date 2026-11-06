<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Transport\Api;

use app\shared\Transport\Http\Resource;

/**
 * 浏览器通信客户端的生效快照（契约偏差 D8）：模式由服务端裁决，前端只消费节奏。
 */
final class CommunicationSettingsResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['effective_mode', 'status', 'settings'], 'properties' => [
            'effective_mode' => ['type' => 'string', 'enum' => ['polling', 'realtime']],
            'status' => ['type' => 'string', 'enum' => ['ready', 'degraded']],
            'settings' => ['type' => 'object'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        if (!is_array($data)) return ['effective_mode' => 'polling', 'status' => 'degraded', 'settings' => []];
        return [
            'effective_mode' => (string) ($data['effective_mode'] ?? 'polling'),
            'status' => (string) ($data['status'] ?? 'degraded'),
            'settings' => is_array($data['settings'] ?? null) ? $data['settings'] : [],
        ];
    }
}
