<?php

declare(strict_types=1);

namespace app\modules\Operations\Runtime\Public;

final class WorkerHeartbeat
{
    private string $workerId;
    private string $workerType;
    private string $state;
    private string $releaseId;
    private string $schemaVersion;
    private string $handlerCatalogHash;
    private int $jobsProcessed;
    private \DateTimeImmutable $heartbeatAt;

    public function __construct(
        string $workerId,
        string $workerType,
        string $state,
        string $releaseId,
        string $schemaVersion,
        string $handlerCatalogHash,
        int $jobsProcessed,
        \DateTimeImmutable $heartbeatAt
    ) {
        if ($workerId === '' || strlen($workerId) > 128) {
            throw new \InvalidArgumentException('worker_id 长度必须为 1..128');
        }
        if (!preg_match('/^[a-z][a-z0-9_.-]*$/', $workerType)) {
            throw new \InvalidArgumentException('worker_type 不合法');
        }
        WorkerState::assert($state);
        if ($jobsProcessed < 0) {
            throw new \InvalidArgumentException('jobs_processed 不能为负数');
        }

        $this->workerId = $workerId;
        $this->workerType = $workerType;
        $this->state = $state;
        $this->releaseId = $releaseId;
        $this->schemaVersion = $schemaVersion;
        $this->handlerCatalogHash = $handlerCatalogHash;
        $this->jobsProcessed = $jobsProcessed;
        $this->heartbeatAt = $heartbeatAt;
    }

    public function workerId(): string { return $this->workerId; }
    public function workerType(): string { return $this->workerType; }
    public function state(): string { return $this->state; }
    public function releaseId(): string { return $this->releaseId; }
    public function schemaVersion(): string { return $this->schemaVersion; }
    public function handlerCatalogHash(): string { return $this->handlerCatalogHash; }
    public function jobsProcessed(): int { return $this->jobsProcessed; }
    public function heartbeatAt(): \DateTimeImmutable { return $this->heartbeatAt; }
}
