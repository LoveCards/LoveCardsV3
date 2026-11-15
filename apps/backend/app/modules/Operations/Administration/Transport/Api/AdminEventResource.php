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
        if (!is_array($data)) return [];
        $deliveries = is_array($data['deliveries'] ?? null) ? $data['deliveries'] : [];
        return [
            'id' => (int) ($data['id'] ?? 0),
            'event_type' => (string) ($data['event_type'] ?? ''),
            'event_version' => (int) ($data['event_version'] ?? 0),
            'aggregate_type' => (string) ($data['aggregate_type'] ?? ''),
            'aggregate_key' => (string) ($data['aggregate_key'] ?? ''),
            'occurred_at' => (string) ($data['occurred_at'] ?? ''),
            'created_at' => (string) ($data['created_at'] ?? ''),
            'deliveries' => array_map(static fn (array $delivery): array => [
                'id' => (int) ($delivery['id'] ?? 0),
                'event_id' => (int) ($delivery['event_id'] ?? 0),
                'handler_key' => (string) ($delivery['handler_key'] ?? ''),
                'queue_name' => (string) ($delivery['queue_name'] ?? ''),
                'status' => (string) ($delivery['status'] ?? ''),
                'attempt_count' => (int) ($delivery['attempt_count'] ?? 0),
                'policy_decision' => $delivery['policy_decision'] ?? null,
                'last_error' => $delivery['last_error'] ?? null,
            ], $deliveries),
        ];
    }
}
