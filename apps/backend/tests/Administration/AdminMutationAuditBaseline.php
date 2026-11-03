<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/shared/Observability/AuditLogger.php';
require_once __DIR__ . '/../../app/shared/Observability/AdminMutationAudit.php';

use app\shared\Observability\AdminMutationAudit;
use app\shared\Observability\AuditLogger;

final class RecordingAuditLogger implements AuditLogger
{
    /** @var array<int,array<string,mixed>> */
    public array $records = [];

    public function record(?int $actorId, string $action, string $resourceType, ?string $resourceId, array $metadata = []): void
    {
        $this->records[] = compact('actorId', 'action', 'resourceType', 'resourceId', 'metadata');
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$logger = new RecordingAuditLogger();
$audit = new AdminMutationAudit($logger);
$count = $audit->recordChanges(
    7,
    'admin.card.moderate',
    'card',
    [41 => ['id' => 41, 'status' => 0, 'updated_at' => 'before'], 42 => ['id' => 42, 'status' => 1]],
    [41 => ['id' => 41, 'status' => 0, 'updated_at' => 'after'], 42 => ['id' => 42, 'status' => 2]],
    '127.0.0.1'
);

$assert($count === 1, 'only real state changes may be audited');
$assert(count($logger->records) === 1, 'no-op resources must not create audit rows');
$record = $logger->records[0];
$assert($record['actorId'] === 7, 'audit actor must be preserved');
$assert($record['resourceType'] === 'card' && $record['resourceId'] === '42', 'audit resource must be addressable');
$assert(!array_key_exists('reason', $record['metadata']), 'administration audit metadata must not contain a manual reason');
$assert($record['metadata']['before']['status'] === 1 && $record['metadata']['after']['status'] === 2, 'audit before/after must be persisted');
$assert($record['metadata']['_ip'] === '127.0.0.1', 'IP must flow through the central hashing boundary');

$silentCount = $audit->recordChanges(
    7,
    'admin.comment.update',
    'comment',
    [9 => ['id' => 9, 'content' => 'before']],
    [9 => ['id' => 9, 'content' => 'after']],
    '127.0.0.1'
);
$assert($silentCount === 1, 'ordinary administration edits must be audited automatically');
$silentRecord = $logger->records[1];
$assert(!array_key_exists('reason', $silentRecord['metadata']), 'automatic audit must not synthesize an absent reason');
$assert($silentRecord['metadata']['before']['content'] === 'before', 'silent audit must preserve the before snapshot');
$assert($silentRecord['metadata']['after']['content'] === 'after', 'silent audit must preserve the after snapshot');

$failingAudit = new AdminMutationAudit(new class implements AuditLogger {
    public function record(?int $actorId, string $action, string $resourceType, ?string $resourceId, array $metadata = []): void
    {
        throw new RuntimeException('audit unavailable');
    }
});
try {
    $failingAudit->recordChanges(7, 'admin.card.update', 'card', [41 => ['id' => 41]], []);
    throw new RuntimeException('audit sink failure must propagate');
} catch (RuntimeException $exception) {
    $assert($exception->getMessage() === 'audit unavailable', 'audit sink failure must remain fail closed');
}

$serviceActions = [
    'Content/Cards/Service/Cards.php' => ['admin.card.update', 'admin.card.delete', 'admin.card.batch.'],
    'Content/Comments/Service/Comments.php' => ['admin.comment.update', 'admin.comment.moderate', 'admin.comment.pin', 'admin.comment.unpin', 'admin.comment.delete', 'admin.comment.batch.'],
    'Content/Tags/Service/Tags.php' => ['admin.tag.update', 'admin.tag.delete', 'admin.tag.batch.'],
    'Platform/User/Service/Users.php' => ['admin.user.update', 'admin.user.delete', 'admin.user.batch.'],
    'Platform/Rbac/Service/Roles.php' => ['admin.role.create', 'admin.role.update', 'admin.role.delete', 'admin.role.assign_grants', 'admin.role.batch.', 'admin.role.reseed'],
];
foreach ($serviceActions as $relative => $actions) {
    $source = file_get_contents(__DIR__ . '/../../app/modules/' . $relative);
    foreach ($actions as $action) {
        $assert(str_contains($source, $action), $relative . ' must audit ' . $action);
    }
}

$auditSink = file_get_contents(__DIR__ . '/../../app/shared/Observability/ThinkDbAuditLogger.php');
$assert(str_contains($auditSink, "'request_id' => \$context->requestId()"), 'audit sink must persist server request id');
$assert(str_contains($auditSink, "'correlation_id' => \$context->correlationId()"), 'audit sink must persist server correlation id');

fwrite(STDOUT, "Admin mutation audit baseline passed.\n");
