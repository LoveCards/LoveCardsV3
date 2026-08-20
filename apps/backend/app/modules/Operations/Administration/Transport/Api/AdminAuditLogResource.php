<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AdminAuditLogResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'id' => ['type' => 'integer'], 'actor_id' => ['type' => 'integer', 'nullable' => true],
            'action' => ['type' => 'string'], 'resource_type' => ['type' => 'string', 'nullable' => true],
            'resource_id' => ['type' => 'string', 'nullable' => true], 'request_id' => ['type' => 'string', 'nullable' => true],
            'correlation_id' => ['type' => 'string', 'nullable' => true], 'payload' => ['type' => 'object'], 'created_at' => ['type' => 'string'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : [];
        if (isset($data['payload']) && is_string($data['payload'])) {
            $decoded = json_decode($data['payload'], true);
            $data['payload'] = is_array($decoded) ? $decoded : [];
        }
        unset($data['ip_hash']);
        return $data;
    }
}
