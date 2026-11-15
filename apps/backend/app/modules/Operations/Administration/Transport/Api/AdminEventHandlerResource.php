<?php

declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 当前 Release 的注册处理能力；不将其误称为发布入口目录。 */
final class AdminEventHandlerResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'event_type' => ['type' => 'string'], 'event_version' => ['type' => 'integer'],
            'handler_key' => ['type' => 'string'], 'queue_name' => ['type' => 'string'],
            'execution_mode' => ['type' => 'string'], 'max_attempts' => ['type' => 'integer'],
            'signal_key' => ['type' => ['string', 'null']],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        if (!is_array($data)) return [];
        return [
            'event_type' => (string) ($data['event_type'] ?? ''),
            'event_version' => (int) ($data['event_version'] ?? 0),
            'handler_key' => (string) ($data['handler_key'] ?? ''),
            'queue_name' => (string) ($data['queue_name'] ?? ''),
            'execution_mode' => (string) ($data['execution_mode'] ?? ''),
            'max_attempts' => (int) ($data['max_attempts'] ?? 0),
            'signal_key' => $data['signal']['key'] ?? null,
        ];
    }
}
