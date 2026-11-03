<?php

namespace app\modules\Theme\Transport\Api;

use app\shared\Transport\Http\PublicResource;

final class ThemeResource extends PublicResource
{
    public static function make($value): array
    {
        $data = parent::make($value);
        return [
            'id' => (string)($data['id'] ?? ''),
            'name' => (string)($data['name'] ?? ''),
            'version' => (string)($data['version'] ?? ''),
            'mode' => (string)($data['mode'] ?? 'spa'),
            'release' => (string)($data['release'] ?? ''),
            'author' => (string)($data['author'] ?? ''),
            'description' => (string)($data['description'] ?? ''),
            'active' => (bool)($data['active'] ?? false),
        ];
    }

    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'required' => ['id', 'name', 'version', 'mode', 'release', 'author', 'description', 'active'], 'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'version' => ['type' => 'string'],
            'mode' => ['type' => 'string', 'enum' => ['spa']],
            'release' => ['type' => 'string'],
            'author' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'active' => ['type' => 'boolean'],
        ], 'additionalProperties' => false];
    }
}
