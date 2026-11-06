<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Transport\Api;

use app\shared\Transport\Http\Resource;

/** Gateway source-poll 回源响应：只含游标与计数，不含业务内容（契约 §4.5）。 */
final class InternalNotificationsPollResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['latest_cursor', 'newer'],
            'properties' => [
                'latest_cursor' => ['type' => ['string', 'null']],
                'newer' => ['type' => 'integer'],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : (array) $data;
        return [
            'latest_cursor' => isset($data['latest_cursor']) ? (string) $data['latest_cursor'] : null,
            'newer' => max(0, (int) ($data['newer'] ?? 0)),
        ];
    }
}
