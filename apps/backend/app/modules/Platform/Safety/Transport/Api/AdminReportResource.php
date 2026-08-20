<?php
declare(strict_types=1);

namespace app\modules\Platform\Safety\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AdminReportResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'id' => ['type' => 'integer'], 'reporter_id' => ['type' => 'integer'],
            'target_type' => ['type' => 'string'], 'target_id' => ['type' => 'string'],
            'reason' => ['type' => 'string'], 'details' => ['type' => 'string', 'nullable' => true],
            'evidence_snapshot' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => false, 'properties' => [
                'type' => ['type' => 'string'],
                'captured' => ['type' => 'object', 'additionalProperties' => false, 'properties' => self::evidenceFields()],
            ]],
            'status' => ['type' => 'string'], 'assignee_id' => ['type' => 'integer', 'nullable' => true],
            'resolution' => ['type' => 'string', 'nullable' => true], 'created_at' => ['type' => 'string'],
            'updated_at' => ['type' => 'string'], 'resolved_at' => ['type' => 'string', 'nullable' => true],
            'events' => ['type' => 'array', 'items' => ['type' => 'object']],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $data = is_array($data) ? $data : [];
        $fields = [
            'id', 'reporter_id', 'target_type', 'target_id', 'reason', 'details', 'evidence_snapshot',
            'status', 'assignee_id', 'resolution', 'created_at', 'updated_at', 'resolved_at', 'events',
        ];
        $data = array_intersect_key($data, array_flip($fields));
        if (isset($data['evidence_snapshot']) && is_array($data['evidence_snapshot'])) {
            $captured = is_array($data['evidence_snapshot']['captured'] ?? null)
                ? array_intersect_key($data['evidence_snapshot']['captured'], self::evidenceFields())
                : [];
            $data['evidence_snapshot'] = [
                'type' => (string) ($data['evidence_snapshot']['type'] ?? ''),
                'captured' => $captured,
            ];
        }
        if (isset($data['events']) && is_array($data['events'])) {
            $data['events'] = array_map(static function ($event): array {
                $event = is_array($event) ? $event : [];
                if (isset($event['payload']) && is_string($event['payload'])) {
                    $decoded = json_decode($event['payload'], true);
                    $event['payload'] = is_array($decoded) ? $decoded : [];
                }
                return array_intersect_key($event, array_flip([
                    'id', 'report_id', 'actor_id', 'event_type', 'payload', 'created_at',
                ]));
            }, $data['events']);
        }
        return $data;
    }

    private static function evidenceFields(): array
    {
        $nullableString = ['type' => 'string', 'nullable' => true];
        return [
            'id' => ['type' => 'integer'], 'number' => ['type' => 'string'], 'username' => ['type' => 'string'],
            'user_id' => ['type' => 'integer'], 'pid' => ['type' => 'integer'], 'conversation_id' => ['type' => 'integer'],
            'sender_id' => ['type' => 'integer'], 'status' => ['type' => 'integer'], 'upload_status' => ['type' => 'string'],
            'content' => $nullableString, 'content_sha256' => ['type' => 'string'],
            'body' => $nullableString, 'body_sha256' => ['type' => 'string'], 'created_at' => $nullableString,
            'hash' => ['type' => 'string'], 'mime_type' => ['type' => 'string'], 'file_url' => $nullableString,
        ];
    }
}
