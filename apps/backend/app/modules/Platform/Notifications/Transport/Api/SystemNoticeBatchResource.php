<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 只向发布者返回自身的冻结内容与真实处理回执。 */
final class SystemNoticeBatchResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        $properties = [
            'id' => ['type' => 'integer'], 'request_key' => ['type' => 'string'],
            'generation' => ['type' => 'integer'],
            'audience' => ['type' => 'string', 'enum' => ['selected', 'all']],
            'recipient_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'recipients' => ['type' => 'array', 'items' => SystemNoticeRecipientResource::schema()],
            'message' => ['type' => 'string'], 'action_url' => ['type' => 'string', 'nullable' => true],
            'severity' => ['type' => 'string', 'enum' => ['info', 'warning']],
            'status' => ['type' => 'string', 'enum' => ['prepared', 'queued', 'processing', 'completed', 'partial_failure', 'failed']],
            'recipient_count' => ['type' => 'integer'], 'generated_count' => ['type' => 'integer'],
            'skipped_count' => ['type' => 'integer'], 'pending_count' => ['type' => 'integer'],
            'failed_count' => ['type' => 'integer'], 'created_at' => ['type' => 'string'],
            'confirmed_at' => ['type' => 'string', 'nullable' => true],
            'completed_at' => ['type' => 'string', 'nullable' => true],
            'can_confirm' => ['type' => 'boolean'], 'can_retry' => ['type' => 'boolean'],
            'diagnostic_event_id' => ['type' => 'integer', 'nullable' => true],
        ];
        return ['type' => 'object', 'additionalProperties' => false, 'required' => array_keys($properties), 'properties' => $properties];
    }

    public static function make($value): array
    {
        $row = (array) self::normalize($value);
        $out = [];
        foreach (['id', 'generation', 'recipient_count', 'generated_count', 'skipped_count', 'pending_count', 'failed_count'] as $key) $out[$key] = (int) $row[$key];
        foreach (['request_key', 'audience', 'message', 'severity', 'status', 'created_at'] as $key) $out[$key] = (string) $row[$key];
        foreach (['action_url', 'confirmed_at', 'completed_at'] as $key) $out[$key] = isset($row[$key]) ? (string) $row[$key] : null;
        $out['diagnostic_event_id'] = isset($row['diagnostic_event_id']) ? (int) $row['diagnostic_event_id'] : null;
        $out['can_confirm'] = (bool) $row['can_confirm'];
        $out['can_retry'] = (bool) $row['can_retry'];
        $out['recipient_ids'] = array_map('intval', $row['recipient_ids']);
        $out['recipients'] = SystemNoticeRecipientResource::collection($row['recipients']);
        return $out;
    }
}
