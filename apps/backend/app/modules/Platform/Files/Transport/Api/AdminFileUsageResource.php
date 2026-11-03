<?php
declare(strict_types=1);

namespace app\modules\Platform\Files\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AdminFileUsageResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'file' => ['type' => 'object', 'additionalProperties' => false, 'properties' => self::fileSchema()],
            'references' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => self::referenceSchema()]],
            'orphaned' => ['type' => 'boolean'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : [];
        $file = self::only(is_array($data['file'] ?? null) ? $data['file'] : [], array_keys(self::fileSchema()));
        $references = [];
        foreach ((array) ($data['references'] ?? []) as $reference) {
            if (is_array($reference)) $references[] = self::only($reference, array_keys(self::referenceSchema()));
        }
        return ['file' => $file, 'references' => $references, 'orphaned' => (bool) ($data['orphaned'] ?? ($references === []))];
    }

    private static function fileSchema(): array
    {
        $nullableString = ['type' => 'string', 'nullable' => true];
        return [
            'id' => ['type' => 'integer'], 'hash' => ['type' => 'string'], 'channel_slug' => ['type' => 'string'],
            'user_id' => ['type' => 'integer', 'nullable' => true], 'is_public' => ['type' => 'integer'],
            'scene' => ['type' => 'string'], 'original_name' => ['type' => 'string'], 'file_url' => ['type' => 'string'],
            'file_size' => ['type' => 'integer'], 'file_ext' => ['type' => 'string'], 'mime_type' => ['type' => 'string'],
            'status' => ['type' => 'integer'], 'upload_status' => ['type' => 'string', 'nullable' => true],
            'expire_at' => $nullableString, 'purge_status' => $nullableString, 'purge_attempts' => ['type' => 'integer'],
            'purge_requested_at' => $nullableString, 'purge_storage_deleted_at' => $nullableString,
            'purge_error' => $nullableString, 'created_at' => $nullableString, 'updated_at' => $nullableString,
            'deleted_at' => $nullableString,
        ];
    }

    private static function referenceSchema(): array
    {
        return [
            'resource_type' => ['type' => 'string'], 'resource_id' => ['type' => 'string'],
            'purpose' => ['type' => 'string'], 'is_public' => ['type' => 'boolean'], 'created_at' => ['type' => 'string'],
        ];
    }

    private static function only(array $value, array $fields): array
    {
        return array_intersect_key($value, array_flip($fields));
    }
}
