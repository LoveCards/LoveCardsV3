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

    public function __construct(string $handlerKey, string $queueName, string $executionMode, private ?array $signal = null, private ?array $signalOrigin = null)
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

    public static function fromRegistration(HandlerRegistration $registration, ?array $signalOrigin = null): self
    {
        $signal = $registration->signal();
        // 续批不是第二份目录注册，只继承父信号的元数据与资格。
        if ($signal === null && $signalOrigin !== null && $registration->queueName() === 'realtime') {
            $signal = $signalOrigin['signal'] ?? null;
        }
        if ($signalOrigin !== null && ($signal['key'] ?? null) !== ($signalOrigin['key'] ?? null)) {
            throw new \InvalidArgumentException('续批不能复用其他信号的发送资格');
        }
        return new self(
            $registration->handlerKey(),
            $registration->queueName(),
            $registration->executionMode(),
            $signal,
            $signalOrigin
        );
    }

    public function handlerKey(): string { return $this->handlerKey; }
    public function queueName(): string { return $this->queueName; }
    public function executionMode(): string { return $this->executionMode; }
    public function signal(): ?array { return $this->signal; }
    public function signalOrigin(): ?array { return $this->signalOrigin; }
}
