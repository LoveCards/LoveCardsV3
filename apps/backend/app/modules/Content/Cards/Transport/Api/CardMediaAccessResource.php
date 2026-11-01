<?php
declare(strict_types=1);
namespace app\modules\Content\Cards\Transport\Api;

use app\shared\Transport\Http\Resource;

final class CardMediaAccessResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['media', 'cover_url', 'expires_in'], 'properties' => [
            'cover_url' => ['type' => 'string', 'nullable' => true],
            'media' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['file_id', 'url'], 'properties' => [
                'file_id' => ['type' => 'integer'], 'url' => ['type' => 'string'],
            ]]],
            'expires_in' => ['type' => 'integer'],
        ]];
    }

    public static function make($value): array
    {
        return ['media' => array_values($value['media'] ?? []), 'cover_url' => $value['cover_url'] ?? null, 'expires_in' => (int) ($value['expires_in'] ?? 0)];
    }
}
