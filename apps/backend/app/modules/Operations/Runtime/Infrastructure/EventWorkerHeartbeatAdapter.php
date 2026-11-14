<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Public\WorkerHeartbeat;
use app\modules\Operations\Runtime\Public\WorkerHeartbeatStore;
use app\modules\Platform\Eventing\Contract\EventWorkerHeartbeatPort;

/** Event Worker 心跳适配现有 Runtime 台账，不增加第二份状态。 */
final class EventWorkerHeartbeatAdapter implements EventWorkerHeartbeatPort
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
    ): void {
        app(WorkerHeartbeatStore::class)->record(new WorkerHeartbeat(
            $workerId, 'eventing', $state, $releaseId, $schemaVersion,
            $handlerCatalogHash, $queueNames, $jobsProcessed, $heartbeatAt
        ));
    }
}
