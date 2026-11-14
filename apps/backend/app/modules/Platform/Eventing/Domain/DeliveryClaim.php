<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Domain;

use app\modules\Platform\Eventing\Public\ExecutionMode;

final class DeliveryClaim
{
    private string $workerId;
    private array $queueNames;
    private array $executionModes;
    private int $limit;
    private string $claimToken;
    private \DateTimeImmutable $now;
    private \DateTimeImmutable $leaseUntil;
    private string $consumerMode;
    private ?string $triggerSource;

    public function __construct(
        string $workerId,
        array $queueNames,
        array $executionModes,
        int $limit,
        string $claimToken,
        \DateTimeImmutable $now,
        \DateTimeImmutable $leaseUntil,
        string $consumerMode,
        ?string $triggerSource = null
    ) {
        if ($workerId === '' || strlen($workerId) > 128) {
            throw new \InvalidArgumentException('worker_id 长度必须为 1..128');
        }
        if ($queueNames === []) {
            throw new \InvalidArgumentException('queue_names 不能为空');
        }
        foreach ($queueNames as $queueName) {
            if (!is_string($queueName) || !preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $queueName)) {
                throw new \InvalidArgumentException('queue_name 不合法');
            }
        }
        if ($executionModes === []) {
            throw new \InvalidArgumentException('execution_modes 不能为空');
        }
        foreach ($executionModes as $mode) {
            if (!is_string($mode)) {
                throw new \InvalidArgumentException('execution_mode 必须是字符串');
            }
            ExecutionMode::assert($mode);
        }
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('claim limit 必须为 1..100');
        }
        if (!preg_match('/^[a-f0-9]{32,64}$/', $claimToken)) {
            throw new \InvalidArgumentException('claim_token 必须是 32..64 位十六进制字符串');
        }
        if ($leaseUntil <= $now) {
            throw new \InvalidArgumentException('lease_until 必须晚于 now');
        }
        if (!in_array($consumerMode, ['scheduled', 'resident'], true)) {
            throw new \InvalidArgumentException('consumer_mode 不合法');
        }
        if (($consumerMode === 'scheduled' && !in_array($triggerSource, ['cli', 'http'], true))
            || ($consumerMode === 'resident' && $triggerSource !== null)) {
            throw new \InvalidArgumentException('trigger_source 与 consumer_mode 不匹配');
        }

        $this->workerId = $workerId;
        $this->queueNames = array_values(array_unique($queueNames));
        $this->executionModes = array_values(array_unique($executionModes));
        $this->limit = $limit;
        $this->claimToken = $claimToken;
        $this->now = $now;
        $this->leaseUntil = $leaseUntil;
        $this->consumerMode = $consumerMode;
        $this->triggerSource = $triggerSource;
    }

    public function workerId(): string { return $this->workerId; }
    public function queueNames(): array { return $this->queueNames; }
    public function executionModes(): array { return $this->executionModes; }
    public function limit(): int { return $this->limit; }
    public function claimToken(): string { return $this->claimToken; }
    public function now(): \DateTimeImmutable { return $this->now; }
    public function leaseUntil(): \DateTimeImmutable { return $this->leaseUntil; }
    public function consumerMode(): string { return $this->consumerMode; }
    public function triggerSource(): ?string { return $this->triggerSource; }
}
