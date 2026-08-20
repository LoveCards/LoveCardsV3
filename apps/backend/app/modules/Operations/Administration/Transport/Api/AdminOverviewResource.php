<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AdminOverviewResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'users' => ['type' => 'integer'], 'cards' => ['type' => 'integer'],
            'comments' => ['type' => 'integer'], 'reports_open' => ['type' => 'integer'],
            'notifications_unread' => ['type' => 'integer'], 'outbox_pending' => ['type' => 'integer'],
            'files_pending' => ['type' => 'integer'], 'runtime' => ['type' => 'array'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        if (!is_array($data)) return [];
        $data['runtime'] = array_values(is_array($data['runtime'] ?? null) ? $data['runtime'] : []);
        return $data;
    }
}
