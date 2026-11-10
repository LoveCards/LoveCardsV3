<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Contract;

/** Event Worker 心跳输出端口，由 Runtime 适配持久化。 */
interface EventWorkerHeartbeatPort
{
    public function record(
        string $workerId,
        string $state,
        string $releaseId,
        string $schemaVersion,
        string $handlerCatalogHash,
        array $queueNames,
        int $jobsProcessed,
        \DateTimeImmutable $heartbeatAt
    ): void;
}
