<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Domain;

use app\modules\Platform\Eventing\Public\ExecutionMode;
use app\modules\Platform\Eventing\Public\HandlerRegistration;

final class DeliveryDefinition
{
    private string $handlerKey;
    private string $queueName;
    private string $executionMode;

    public function __construct(string $handlerKey, string $queueName, string $executionMode)
    {
        if (!preg_match('/^[a-z][a-z0-9_.-]{0,127}$/', $handlerKey)) {
            throw new \InvalidArgumentException('handler_key 不合法');
        }
        if (!preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $queueName)) {
            throw new \InvalidArgumentException('queue_name 不合法');
        }
        ExecutionMode::assert($executionMode);
        $this->handlerKey = $handlerKey;
        $this->queueName = $queueName;
        $this->executionMode = $executionMode;
    }

    public static function fromRegistration(HandlerRegistration $registration): self
    {
        return new self(
            $registration->handlerKey(),
            $registration->queueName(),
            $registration->executionMode()
        );
    }

    public function handlerKey(): string { return $this->handlerKey; }
    public function queueName(): string { return $this->queueName; }
    public function executionMode(): string { return $this->executionMode; }
}
