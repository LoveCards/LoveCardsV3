<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Transport\Api;

use app\shared\Transport\Http\Resource;

final class SystemNoticeLatestBatchResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['batch'], 'properties' => [
            'batch' => SystemNoticeBatchResource::schema() + ['nullable' => true],
        ]];
    }
    public static function make($value): array
    {
        return ['batch' => $value === null ? null : SystemNoticeBatchResource::make($value)];
    }
}
