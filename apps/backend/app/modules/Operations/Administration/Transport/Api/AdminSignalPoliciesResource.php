<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 主动更新目录：代码注册与持久开关的组合视图。 */
final class AdminSignalPoliciesResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['revision', 'signals'], 'properties' => [
            'revision' => ['type' => 'integer'],
            'signals' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false,
                'required' => ['key', 'topic', 'owner', 'display_name', 'impact_text', 'default_enabled', 'enabled', 'event_type', 'event_version', 'handler_key'],
                'properties' => [
                    'key' => ['type' => 'string'], 'topic' => ['type' => 'string'], 'owner' => ['type' => 'string'],
                    'display_name' => ['type' => 'string'], 'impact_text' => ['type' => 'string'],
                    'default_enabled' => ['type' => 'boolean'], 'enabled' => ['type' => 'boolean'],
                    'event_type' => ['type' => 'string'], 'event_version' => ['type' => 'integer'],
                    'handler_key' => ['type' => 'string'],
                ],
            ]],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        return ['revision' => (int) ($data['revision'] ?? 0), 'signals' => array_values($data['signals'] ?? [])];
    }
}
