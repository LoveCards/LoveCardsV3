<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

final class HandlerRegistration
{
    private string $eventType;
    private int $eventVersion;
    private string $handlerKey;
    private string $queueName;
    private string $executionMode;
    private EventHandler $handler;
    private int $maxAttempts;
    private array $retryDelays;

    public function __construct(
        string $eventType,
        int $eventVersion,
        string $handlerKey,
        string $queueName,
        string $executionMode,
        EventHandler $handler,
        int $maxAttempts = 5,
        array $retryDelays = [5, 30, 300, 1800]
    ) {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,99}$/', $eventType)) {
            throw new \InvalidArgumentException('Handler event_type 不合法');
        }
        if ($eventVersion < 1) {
            throw new \InvalidArgumentException('Handler event_version 必须大于 0');
        }
        if (!preg_match('/^[a-z][a-z0-9_.-]{0,127}$/', $handlerKey)) {
            throw new \InvalidArgumentException('handler_key 不合法');
        }
        if (!preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $queueName)) {
            throw new \InvalidArgumentException('queue_name 不合法');
        }
        ExecutionMode::assert($executionMode);
        if ($maxAttempts < 1 || $maxAttempts > 100) {
            throw new \InvalidArgumentException('max_attempts 必须为 1..100');
        }
        if ($retryDelays === []) {
            throw new \InvalidArgumentException('retry_delays 不能为空');
        }
        foreach ($retryDelays as $delay) {
            if (!is_int($delay) || $delay < 0 || $delay > 86400 * 30) {
                throw new \InvalidArgumentException('retry_delays 必须是 0..2592000 的整数');
            }
        }

        $this->eventType = $eventType;
        $this->eventVersion = $eventVersion;
        $this->handlerKey = $handlerKey;
        $this->queueName = $queueName;
        $this->executionMode = $executionMode;
        $this->handler = $handler;
        $this->maxAttempts = $maxAttempts;
        $this->retryDelays = array_values($retryDelays);
    }

    public function eventType(): string { return $this->eventType; }
    public function eventVersion(): int { return $this->eventVersion; }
    public function handlerKey(): string { return $this->handlerKey; }
    public function queueName(): string { return $this->queueName; }
    public function executionMode(): string { return $this->executionMode; }
    public function handler(): EventHandler { return $this->handler; }
    public function maxAttempts(): int { return $this->maxAttempts; }

    public function matches(EventEnvelope $event): bool
    {
        return $this->eventType === $event->eventType()
            && $this->eventVersion === $event->eventVersion();
    }

    public function retryDelayForAttempt(int $attempt): int
    {
        $index = max(0, min($attempt - 1, count($this->retryDelays) - 1));
        return $this->retryDelays[$index];
    }

    public function catalogData(): array
    {
        return [
            'event_type' => $this->eventType,
            'event_version' => $this->eventVersion,
            'handler_key' => $this->handlerKey,
            'queue_name' => $this->queueName,
            'execution_mode' => $this->executionMode,
            'max_attempts' => $this->maxAttempts,
            'retry_delays' => $this->retryDelays,
        ];
    }
}
