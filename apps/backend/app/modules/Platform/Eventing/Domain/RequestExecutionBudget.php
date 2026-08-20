<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Domain;

final class RequestExecutionBudget
{
    private string $workerId;
    private int $maxJobs;
    private int $maxMilliseconds;
    private int $leaseSeconds;
    private array $queueNames;

    public function __construct(
        string $workerId,
        int $maxJobs = 2,
        int $maxMilliseconds = 50,
        int $leaseSeconds = 30,
        array $queueNames = ['critical', 'default']
    ) {
        if ($workerId === '' || strlen($workerId) > 128) {
            throw new \InvalidArgumentException('Request Executor worker_id 长度必须为 1..128');
        }
        if ($maxJobs < 1 || $maxJobs > 20) {
            throw new \InvalidArgumentException('Request Executor max_jobs 必须为 1..20');
        }
        if ($maxMilliseconds < 1 || $maxMilliseconds > 1000) {
            throw new \InvalidArgumentException('Request Executor max_milliseconds 必须为 1..1000');
        }
        if ($leaseSeconds < 1 || $leaseSeconds > 3600) {
            throw new \InvalidArgumentException('Request Executor lease_seconds 必须为 1..3600');
        }
        if ($queueNames === []) {
            throw new \InvalidArgumentException('Request Executor queue_names 不能为空');
        }
        foreach ($queueNames as $queueName) {
            if (!is_string($queueName) || !preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $queueName)) {
                throw new \InvalidArgumentException('Request Executor queue_name 不合法');
            }
        }
        $this->workerId = $workerId;
        $this->maxJobs = $maxJobs;
        $this->maxMilliseconds = $maxMilliseconds;
        $this->leaseSeconds = $leaseSeconds;
        $this->queueNames = array_values(array_unique($queueNames));
    }

    public function workerId(): string { return $this->workerId; }
    public function maxJobs(): int { return $this->maxJobs; }
    public function maxMilliseconds(): int { return $this->maxMilliseconds; }
    public function leaseSeconds(): int { return $this->leaseSeconds; }
    public function queueNames(): array { return $this->queueNames; }
}
