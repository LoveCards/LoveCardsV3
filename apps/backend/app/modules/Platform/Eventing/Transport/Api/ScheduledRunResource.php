<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Transport\Api;

use app\shared\Transport\Http\Resource;

final class ScheduledRunResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['claimed'],
            'properties' => ['claimed' => ['type' => 'integer']]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        return ['claimed' => (int) ($data['claimed'] ?? 0)];
    }
}
