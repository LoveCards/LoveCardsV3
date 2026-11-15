<?php

declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 管理端事件事实投影；不返回 payload 或领取凭据。 */
final class AdminEventResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'id' => ['type' => 'integer'], 'event_type' => ['type' => 'string'],
            'event_version' => ['type' => 'integer'], 'aggregate_type' => ['type' => 'string'],
            'aggregate_key' => ['type' => 'string'], 'occurred_at' => ['type' => 'string'],
            'created_at' => ['type' => 'string'],
            'deliveries' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false, 'properties' => [
                    'id' => ['type' => 'integer'], 'event_id' => ['type' => 'integer'],
                    'handler_key' => ['type' => 'string'], 'queue_name' => ['type' => 'string'],
                    'status' => ['type' => 'string'], 'attempt_count' => ['type' => 'integer'],
                    'policy_decision' => ['type' => ['string', 'null']],
                    'last_error' => ['type' => ['string', 'null']],
                ],
            ]],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        return is_array($data) ? $data : [];
    }
}
