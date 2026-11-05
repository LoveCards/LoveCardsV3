<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Transport\Api;

use app\shared\Transport\Http\Resource;

final class RealtimeTicketResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'x-typescript-type' => 'RealtimeStreamTicket',
            'additionalProperties' => false,
            'required' => ['ticket', 'endpoint', 'protocol', 'expires_at', 'topics', 'cursor_strategy'],
            'properties' => [
                'ticket' => ['type' => 'string'],
                'endpoint' => ['type' => 'string', 'format' => 'uri'],
                'protocol' => ['type' => 'string', 'enum' => ['lc-realtime-1']],
                'expires_at' => ['type' => 'string', 'format' => 'date-time'],
                'topics' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['notifications', 'messages']]],
                'cursor_strategy' => ['type' => 'string', 'enum' => ['opaque']],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        return [
            'ticket' => (string) ($data['ticket'] ?? ''),
            'endpoint' => (string) ($data['endpoint'] ?? ''),
            'protocol' => (string) ($data['protocol'] ?? 'lc-realtime-1'),
            'expires_at' => (string) ($data['expires_at'] ?? ''),
            'topics' => array_values(array_map('strval', (array) ($data['topics'] ?? []))),
            'cursor_strategy' => (string) ($data['cursor_strategy'] ?? 'opaque'),
        ];
    }
}
