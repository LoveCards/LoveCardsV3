<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Transport\Api;

use app\modules\Platform\Notifications\Contract\NotificationTypeCatalog;
use app\shared\Transport\Http\Resource;

/** 默认主题可直接消费的通知项读取契约。 */
final class NotificationItemResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'id', 'type', 'category', 'actor_id', 'actor', 'actor_state',
                'resource_type', 'resource_id', 'resource_state', 'action_url', 'payload',
                'read_at', 'created_at',
            ],
            'properties' => [
                'id' => ['type' => 'integer'],
                'type' => ['type' => 'string', 'enum' => NotificationTypeCatalog::types()],
                'category' => ['type' => 'string', 'enum' => NotificationTypeCatalog::CATEGORIES],
                'actor_id' => ['type' => 'integer', 'nullable' => true],
                'actor' => [
                    'type' => 'object',
                    'nullable' => true,
                    'additionalProperties' => false,
                    'required' => ['uid', 'username', 'avatar'],
                    'properties' => [
                        'uid' => ['type' => 'string', 'nullable' => true],
                        'username' => ['type' => 'string'],
                        'avatar' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'actor_state' => ['type' => 'string', 'enum' => ['active', 'unavailable', 'system']],
                'resource_type' => ['type' => 'string', 'nullable' => true],
                'resource_id' => ['type' => 'string', 'nullable' => true],
                'resource_state' => ['type' => 'string', 'enum' => ['available', 'deleted', 'restricted', 'unavailable']],
                'action_url' => ['type' => 'string', 'nullable' => true],
                'payload' => ['type' => 'object', 'additionalProperties' => true],
                'read_at' => ['type' => 'string', 'nullable' => true],
                'created_at' => ['type' => 'string'],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : (array) $data;
        try {
            $type = NotificationTypeCatalog::normalizeType((string) ($data['type'] ?? ''));
        } catch (\InvalidArgumentException $exception) {
            throw new \UnexpectedValueException('通知类型不在公开契约内', 0, $exception);
        }
        $actor = is_array($data['actor'] ?? null) ? $data['actor'] : null;
        if ($actor !== null) {
            $actor = [
                'uid' => isset($actor['uid']) ? (string) $actor['uid'] : null,
                'username' => (string) ($actor['username'] ?? ''),
                'avatar' => self::nullableString($actor['avatar'] ?? null),
            ];
        }

        $actorState = (string) ($data['actor_state'] ?? 'unavailable');
        if (!in_array($actorState, ['active', 'unavailable', 'system'], true)) $actorState = 'unavailable';
        $resourceState = (string) ($data['resource_state'] ?? 'unavailable');
        if (!in_array($resourceState, ['available', 'deleted', 'restricted', 'unavailable'], true)) {
            $resourceState = 'unavailable';
        }

        return [
            'id' => (int) ($data['id'] ?? 0),
            'type' => $type,
            'category' => NotificationTypeCatalog::categoryFor($type),
            'actor_id' => isset($data['actor_id']) ? (int) $data['actor_id'] : null,
            'actor' => $actor,
            'actor_state' => $actorState,
            'resource_type' => self::nullableString($data['resource_type'] ?? null),
            'resource_id' => self::nullableString($data['resource_id'] ?? null),
            'resource_state' => $resourceState,
            'action_url' => self::nullableString($data['action_url'] ?? null),
            'payload' => is_array($data['payload'] ?? null) ? $data['payload'] : [],
            'read_at' => self::nullableString($data['read_at'] ?? null),
            'created_at' => (string) ($data['created_at'] ?? ''),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
