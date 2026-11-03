<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AdminDeliveryResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'id' => ['type' => 'integer'], 'event_id' => ['type' => 'integer'], 'event_type' => ['type' => 'string'],
            'handler_key' => ['type' => 'string'], 'queue_name' => ['type' => 'string'], 'execution_mode' => ['type' => 'string'],
            'status' => ['type' => 'string'], 'attempt_count' => ['type' => 'integer'], 'available_at' => ['type' => 'string'],
            'last_error' => ['type' => 'string', 'nullable' => true], 'correlation_id' => ['type' => 'string', 'nullable' => true],
            'created_at' => ['type' => 'string'], 'updated_at' => ['type' => 'string'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : [];
        unset($data['payload']);
        return $data;
    }
}
