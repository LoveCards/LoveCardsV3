<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 一次性 stream ticket 签发响应（契约 §4.4）。 */
final class StreamTicketResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['ticket', 'expires_in', 'connect_url', 'heartbeat_seconds'],
            'properties' => [
                'ticket' => ['type' => 'string'],
                'expires_in' => ['type' => 'integer'],
                'connect_url' => ['type' => 'string'],
                'heartbeat_seconds' => ['type' => 'integer'],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : (array) $data;
        return [
            'ticket' => (string) ($data['ticket'] ?? ''),
            'expires_in' => (int) ($data['expires_in'] ?? 0),
            'connect_url' => (string) ($data['connect_url'] ?? ''),
            'heartbeat_seconds' => (int) ($data['heartbeat_seconds'] ?? 0),
        ];
    }
}
