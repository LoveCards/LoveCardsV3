<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Transport\Api;

use app\modules\Platform\Notifications\Contract\NotificationTypeCatalog;
use app\shared\Transport\Http\Resource;

/** 管理端只读元数据投影；列表与详情共享，未知历史类型保留原值。 */
final class AdminNotificationResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['id', 'type', 'type_label', 'recipient_id', 'actor_id', 'resource_type', 'resource_id', 'read_at', 'created_at'],
            'properties' => [
                'id' => ['type' => 'integer'],
                'type' => ['type' => 'string'],
                'type_label' => ['type' => 'string'],
                'recipient_id' => ['type' => 'integer'],
                'actor_id' => ['type' => 'integer', 'nullable' => true],
                'resource_type' => ['type' => 'string', 'nullable' => true],
                'resource_id' => ['type' => 'string', 'nullable' => true],
                'read_at' => ['type' => 'string', 'nullable' => true],
                'created_at' => ['type' => 'string'],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : (array) $data;
        $type = (string) ($data['type'] ?? '');
        return [
            'id' => (int) ($data['id'] ?? 0),
            'type' => $type,
            'type_label' => NotificationTypeCatalog::labelFor($type),
            'recipient_id' => (int) ($data['recipient_id'] ?? 0),
            'actor_id' => isset($data['actor_id']) ? (int) $data['actor_id'] : null,
            'resource_type' => isset($data['resource_type']) ? (string) $data['resource_type'] : null,
            'resource_id' => isset($data['resource_id']) ? (string) $data['resource_id'] : null,
            'read_at' => isset($data['read_at']) ? (string) $data['read_at'] : null,
            'created_at' => (string) ($data['created_at'] ?? ''),
        ];
    }
}
