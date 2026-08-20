<?php

namespace app\modules\Theme\Transport\Api;

use app\shared\Transport\Http\PublicResource;

final class ThemeConfigResource extends PublicResource
{
    public static function make($value): array
    {
        $data = parent::make($value);
        $theme = is_array($data['theme'] ?? null) ? $data['theme'] : $data;
        return [
            'id' => (string)($theme['id'] ?? ''),
            'name' => (string)($theme['name'] ?? ''),
            'version' => (string)($theme['version'] ?? ''),
            'mode' => (string)($theme['mode'] ?? 'spa'),
            'release' => (string)($theme['release'] ?? ''),
            'config_schema' => is_array($data['config_schema'] ?? null) ? $data['config_schema'] : [],
            'config_values' => is_array($data['config_values'] ?? null) ? $data['config_values'] : [],
        ];
    }

    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'required' => ['id', 'name', 'version', 'mode', 'release', 'config_schema', 'config_values'], 'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'version' => ['type' => 'string'],
            'mode' => ['type' => 'string', 'enum' => ['spa']],
            'release' => ['type' => 'string'],
            'config_schema' => ['type' => 'object', 'additionalProperties' => true],
            'config_values' => ['type' => 'object', 'additionalProperties' => true],
        ], 'additionalProperties' => false];
    }
}
