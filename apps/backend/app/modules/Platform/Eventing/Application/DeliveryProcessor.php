<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Application;

use app\modules\Platform\Eventing\Contract\Clock;
use app\modules\Platform\Eventing\Contract\DeliveryRepository;
use app\modules\Platform\Eventing\Contract\FailureSanitizer;
use app\modules\Platform\Eventing\Contract\OutboxEventRepository;
use app\modules\Platform\Eventing\Domain\ClaimedDelivery;
use app\modules\Platform\Eventing\Domain\DeliveryFailure;
use app\modules\Platform\Eventing\Domain\DeliveryOutcome;
use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\HandlerRegistration;
use app\modules\Platform\Eventing\Public\HandlerRegistry;

final class DeliveryProcessor
{
    private OutboxEventRepository $events;
    private DeliveryRepository $deliveries;
    private HandlerRegistry $handlers;
    private Clock $clock;
    private FailureSanitizer $sanitizer;

    public function __construct(
        OutboxEventRepository $events,
        DeliveryRepository $deliveries,
        HandlerRegistry $handlers,
        Clock $clock,
        FailureSanitizer $sanitizer
    ) {
        $this->events = $events;
        $this->deliveries = $deliveries;
        $this->handlers = $handlers;
        $this->clock = $clock;
        $this->sanitizer = $sanitizer;
    }

    public function process(ClaimedDelivery $delivery): string
    {
        $event = $this->events->findById($delivery->eventId());
        if ($event === null) {
            return $this->fail(
                $delivery,
                null,
                new \UnexpectedValueException('Outbox Event 不存在'),
                true
            );
        }

        $registration = $this->handlers->find($delivery->handlerKey());
        if ($registration === null) {
            return $this->fail(
                $delivery,
                null,
                new \RuntimeException('当前 Release 未注册对应 Event Handler'),
                $delivery->attemptCount() >= 5
            );
        }
        if (!$registration->matches($event)
            || $registration->executionMode() !== $delivery->executionMode()
            || $registration->queueName() !== $delivery->queueName()) {
            return $this->fail(
                $delivery,
                $registration,
                new \UnexpectedValueException('Delivery 与 Handler Catalog 不一致'),
                true
            );
        }

        try {
            $registration->handler()->handle(
                $event,
                new DeliveryContext(
                    $delivery->id(),
                    $delivery->eventId(),
                    $delivery->handlerKey(),
                    $delivery->attemptCount()
                )
            );
        } catch (\Throwable $exception) {
            return $this->fail(
                $delivery,
                $registration,
                $exception,
                $delivery->attemptCount() >= $registration->maxAttempts()
            );
        }

        $completed = $this->deliveries->complete(
            $delivery->id(),
            $delivery->claimToken(),
            $this->clock->now()
        );

        return $completed ? DeliveryOutcome::COMPLETED : DeliveryOutcome::FENCED;
    }

    private function fail(
        ClaimedDelivery $delivery,
        ?HandlerRegistration $registration,
        \Throwable $exception,
        bool $terminal
    ): string {
        $now = $this->clock->now();
        $delay = $terminal ? 0 : ($registration
            ? $registration->retryDelayForAttempt($delivery->attemptCount())
            : 30);
        $availableAt = $delay === 0 ? $now : $now->modify("+{$delay} seconds");
        $failure = new DeliveryFailure($terminal, $this->sanitizer->sanitize($exception), $availableAt);
        $updated = $this->deliveries->fail(
            $delivery->id(),
            $delivery->claimToken(),
            $failure,
            $now
        );
        if (!$updated) {
            return DeliveryOutcome::FENCED;
        }

        return $terminal ? DeliveryOutcome::DEAD : DeliveryOutcome::RETRY_SCHEDULED;
    }
}
