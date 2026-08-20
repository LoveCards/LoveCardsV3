<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Domain;

final class WorkerOptions
{
    private string $workerId;
    private array $queueNames;
    private int $leaseSeconds;
    private int $idleSleepMilliseconds;
    private int $heartbeatMilliseconds;
    private int $maxJobs;
    private int $maxRuntimeMilliseconds;
    private string $releaseId;
    private string $schemaVersion;

    public function __construct(
        string $workerId,
        array $queueNames = ['critical', 'default', 'bulk'],
        int $leaseSeconds = 300,
        int $idleSleepMilliseconds = 500,
        int $heartbeatMilliseconds = 10000,
        int $maxJobs = 0,
        int $maxRuntimeMilliseconds = 0,
        string $releaseId = 'unknown',
        string $schemaVersion = 'unknown'
    ) {
        if ($workerId === '' || strlen($workerId) > 128) throw new \InvalidArgumentException('worker_id 不合法');
        if ($queueNames === []) throw new \InvalidArgumentException('queue_names 不能为空');
        foreach ($queueNames as $queueName) {
            if (!is_string($queueName) || !preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $queueName)) {
                throw new \InvalidArgumentException('queue_name 不合法');
            }
        }
        if ($leaseSeconds < 1 || $leaseSeconds > 86400) throw new \InvalidArgumentException('lease_seconds 不合法');
        if ($idleSleepMilliseconds < 1 || $idleSleepMilliseconds > 60000) throw new \InvalidArgumentException('idle_sleep 不合法');
        if ($heartbeatMilliseconds < 100 || $heartbeatMilliseconds > 300000) throw new \InvalidArgumentException('heartbeat interval 不合法');
        if ($maxJobs < 0 || $maxRuntimeMilliseconds < 0) throw new \InvalidArgumentException('Worker 上限不能为负数');
        $this->workerId = $workerId;
        $this->queueNames = array_values(array_unique($queueNames));
        $this->leaseSeconds = $leaseSeconds;
        $this->idleSleepMilliseconds = $idleSleepMilliseconds;
        $this->heartbeatMilliseconds = $heartbeatMilliseconds;
        $this->maxJobs = $maxJobs;
        $this->maxRuntimeMilliseconds = $maxRuntimeMilliseconds;
        $this->releaseId = $releaseId;
        $this->schemaVersion = $schemaVersion;
    }

    public function workerId(): string { return $this->workerId; }
    public function queueNames(): array { return $this->queueNames; }
    public function leaseSeconds(): int { return $this->leaseSeconds; }
    public function idleSleepMilliseconds(): int { return $this->idleSleepMilliseconds; }
    public function heartbeatMilliseconds(): int { return $this->heartbeatMilliseconds; }
    public function maxJobs(): int { return $this->maxJobs; }
    public function maxRuntimeMilliseconds(): int { return $this->maxRuntimeMilliseconds; }
    public function releaseId(): string { return $this->releaseId; }
    public function schemaVersion(): string { return $this->schemaVersion; }
}
