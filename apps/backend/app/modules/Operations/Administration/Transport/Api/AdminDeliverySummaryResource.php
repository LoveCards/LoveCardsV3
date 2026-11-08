<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AdminDeliverySummaryResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'pending' => ['type' => 'integer'], 'processing' => ['type' => 'integer'],
            'dead' => ['type' => 'integer'], 'skipped' => ['type' => 'integer'],
            'checked_at' => ['type' => 'string'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        return is_array($data) ? $data : [];
    }
}
