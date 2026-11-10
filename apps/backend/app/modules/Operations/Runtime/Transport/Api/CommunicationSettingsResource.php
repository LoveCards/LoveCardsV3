<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Transport\Api;

use app\shared\Transport\Http\Resource;

/**
 * 浏览器通信客户端的模式快照：故障时仍保留管理员所选模式。
 */
final class CommunicationSettingsResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['desired_mode', 'effective_mode', 'status', 'settings'], 'properties' => [
            'desired_mode' => ['type' => 'string', 'enum' => ['polling', 'realtime']],
            'effective_mode' => ['type' => 'string', 'enum' => ['polling', 'realtime']],
            'status' => ['type' => 'string', 'enum' => ['ready', 'degraded']],
            'settings' => ['type' => 'object'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        if (!is_array($data)) return ['desired_mode' => 'polling', 'effective_mode' => 'polling', 'status' => 'degraded', 'settings' => []];
        return [
            'desired_mode' => (string) ($data['desired_mode'] ?? 'polling'),
            'effective_mode' => (string) ($data['effective_mode'] ?? 'polling'),
            'status' => (string) ($data['status'] ?? 'degraded'),
            'settings' => is_array($data['settings'] ?? null) ? $data['settings'] : [],
        ];
    }
}
