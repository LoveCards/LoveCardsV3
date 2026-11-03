<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Transport\Api;

use app\shared\Transport\Http\Resource;

/** Strongly typed unread notification count response. */
final class NotificationUnreadResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['unread'],
            'properties' => ['unread' => ['type' => 'integer']],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : (array) $data;
        return ['unread' => max(0, (int) ($data['unread'] ?? 0))];
    }
}
