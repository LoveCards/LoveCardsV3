<?php

declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AdminReleaseStatusResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'version' => ['type' => 'string'], 'build' => ['type' => 'integer'],
            'release_id' => ['type' => 'string', 'nullable' => true],
        ]];
    }

    public static function make($value): array
    {
        return ['version' => (string) $value['version'], 'build' => (int) $value['build'], 'release_id' => $value['release_id']];
    }
}
