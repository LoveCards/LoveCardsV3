<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AdminNotificationStatsResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'total' => ['type' => 'integer'], 'unread' => ['type' => 'integer'],
            'recipients' => ['type' => 'integer'], 'types' => ['type' => 'array'],
        ]];
    }
    public static function make($value): array { $data = self::normalize($value); return is_array($data) ? $data : []; }
}
