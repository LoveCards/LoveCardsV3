<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Transport\Api;

use app\shared\Transport\Http\Resource;

/** Gateway 校验 stream ticket 后的内部响应（契约 §4.4）。 */
final class InternalTicketVerifyResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['uid', 'cursor_seed', 'connection_ttl_seconds'],
            'properties' => [
                'uid' => ['type' => 'integer'],
                'connection_ttl_seconds' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 60],
                'cursor_seed' => ['type' => ['string', 'null']],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : (array) $data;
        return [
            'uid' => (int) ($data['uid'] ?? 0),
            'connection_ttl_seconds' => (int) ($data['connection_ttl_seconds'] ?? 0),
            'cursor_seed' => isset($data['cursor_seed']) ? (string) $data['cursor_seed'] : null,
        ];
    }
}
