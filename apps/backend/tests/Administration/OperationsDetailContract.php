<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/shared/Transport/Http/Resource.php';
require $root . '/app/modules/Operations/Administration/Transport/Api/AdminDeliveryResource.php';
require $root . '/app/modules/Operations/Administration/Transport/Api/AdminAuditLogResource.php';

use app\modules\Operations\Administration\Transport\Api\AdminAuditLogResource;
use app\modules\Operations\Administration\Transport\Api\AdminDeliveryResource;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$delivery = AdminDeliveryResource::make([
    'id' => 7,
    'event_id' => 11,
    'event_type' => 'content.card_liked',
    'handler_key' => 'notifications.card-liked',
    'queue_name' => 'notifications',
    'execution_mode' => 'async',
    'status' => 'dead',
    'attempt_count' => 3,
    'available_at' => '2026-08-23 00:00:00',
    'last_error' => 'redacted failure',
    'correlation_id' => 'correlation-1',
    'created_at' => '2026-08-23 00:00:00',
    'updated_at' => '2026-08-23 00:00:01',
    'payload' => ['private' => true],
]);
$assert(!isset($delivery['payload']), 'delivery detail must not expose the outbox payload');

$audit = AdminAuditLogResource::make([
    'id' => 9,
    'actor_id' => 2,
    'action' => 'cards.delete',
    'resource_type' => 'card',
    'resource_id' => '41',
    'request_id' => 'request-1',
    'correlation_id' => 'correlation-1',
    'payload' => '{"reason":"policy violation"}',
    'ip_hash' => 'must-not-leak',
    'created_at' => '2026-08-23 00:00:00',
]);
$assert(($audit['payload']['reason'] ?? null) === 'policy violation', 'audit detail must hydrate its controlled payload');
$assert(!isset($audit['ip_hash']), 'audit detail must never expose the IP hash');

$routes = file_get_contents($root . '/app/api/route/operations.php');
$readModel = file_get_contents($root . '/app/modules/Operations/Administration/Service/AdminReadModel.php');
$assert(str_contains($routes, "name('operations.delivery')"), 'delivery detail route must be named and exported');
$assert(str_contains($routes, "name('operations.auditLog')"), 'audit detail route must be named and exported');
$assert(str_contains($readModel, "ApiException::notFound('投递记录不存在')"), 'missing delivery must have a stable not-found response');
$assert(str_contains($readModel, "ApiException::notFound('审计记录不存在')"), 'missing audit log must have a stable not-found response');

fwrite(STDOUT, "PASS operations detail contract\n");
