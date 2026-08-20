<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/shared/Transport/Http/Resource.php';
require $root . '/app/modules/Platform/Files/Transport/Api/AdminFileUsageResource.php';
require $root . '/app/modules/Platform/Safety/Transport/Api/AdminReportResource.php';

use app\modules\Platform\Files\Transport\Api\AdminFileUsageResource;
use app\modules\Platform\Safety\Transport\Api\AdminReportResource;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$usage = AdminFileUsageResource::make([
    'file' => [
        'id' => 7, 'hash' => 'abc123', 'channel_slug' => 'local', 'original_name' => 'photo.jpg',
        'driver_path' => 'private/photo.jpg', 'file_path' => 'private/photo.jpg', 'secret' => 'nope',
        'purge_status' => 'pending',
    ],
    'references' => [[
        'resource_type' => 'card', 'resource_id' => '42', 'purpose' => 'cover',
        'is_public' => true, 'created_at' => '2026-08-19 00:00:00', 'private_note' => 'nope',
    ]],
    'orphaned' => false,
    'internal' => 'nope',
]);
$assert(!isset($usage['file']['driver_path'], $usage['file']['file_path'], $usage['file']['secret']), 'admin file usage must hide storage internals');
$assert(!isset($usage['references'][0]['private_note'], $usage['internal']), 'admin file usage must whitelist nested fields');
$assert($usage['file']['purge_status'] === 'pending', 'admin file usage must expose purge diagnostics');

$report = AdminReportResource::make([
    'id' => 9, 'reporter_id' => 3, 'target_type' => 'message', 'target_id' => '11',
    'reason' => 'abuse', 'details' => 'detail', 'status' => 'reviewing', 'assignee_id' => 5,
    'evidence_snapshot' => ['type' => 'message', 'captured' => [
        'id' => 11, 'body' => 'reported body', 'body_sha256' => 'hash', 'secret' => 'nope',
    ]],
    'events' => [[
        'id' => 1, 'report_id' => 9, 'actor_id' => 5, 'event_type' => 'status_changed',
        'payload' => '{"to_status":"reviewing"}', 'created_at' => '2026-08-19 00:00:00', 'private' => 'nope',
    ]],
    'private' => 'nope',
]);
$assert($report['assignee_id'] === 5, 'admin report must expose assignee');
$assert($report['evidence_snapshot']['captured']['body'] === 'reported body', 'reported message evidence must be available only through report detail');
$assert(!isset($report['evidence_snapshot']['captured']['secret'], $report['events'][0]['private'], $report['private']), 'admin report must whitelist evidence and event fields');

$adminReadModel = file_get_contents($root . '/app/modules/Operations/Administration/Service/AdminReadModel.php');
$assert(str_contains($adminReadModel, "Db::table('file_references')"), 'file usage must use file_references as the source of truth');
$assert(!str_contains($adminReadModel, "!empty(\$file['ref_type'])"), 'file usage must not fall back to the legacy single reference projection');

fwrite(STDOUT, "PASS admin community resource baseline\n");
