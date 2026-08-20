<?php
declare(strict_types=1);

namespace app\shared\Observability;

interface AuditLogger
{
    /** @param array<string,mixed> $metadata */
    public function record(?int $actorId, string $action, string $resourceType, ?string $resourceId, array $metadata = []): void;
}
