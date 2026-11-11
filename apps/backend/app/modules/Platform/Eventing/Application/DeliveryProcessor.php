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
use app\modules\Platform\Eventing\Public\SignalTransportUnavailable;
use app\shared\Observability\LogContext;
use app\shared\Observability\LogContextScope;

final class DeliveryProcessor
{
    private OutboxEventRepository $events;
    private DeliveryRepository $deliveries;
    private HandlerRegistry $handlers;
    private Clock $clock;
    private FailureSanitizer $sanitizer;
    private LogContextScope $logContext;

    public function __construct(
        OutboxEventRepository $events,
        DeliveryRepository $deliveries,
        HandlerRegistry $handlers,
        Clock $clock,
        FailureSanitizer $sanitizer,
        LogContextScope $logContext
    ) {
        $this->events = $events;
        $this->deliveries = $deliveries;
        $this->handlers = $handlers;
        $this->clock = $clock;
        $this->sanitizer = $sanitizer;
        $this->logContext = $logContext;
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
                $delivery->attemptCount() >= ($delivery->retryAttemptLimit() ?? 5)
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
        $attemptLimit = $delivery->retryAttemptLimit() ?? $registration->maxAttempts();
        if ($delivery->attemptCount() > $attemptLimit) {
            return $this->fail(
                $delivery,
                $registration,
                new \RuntimeException('Delivery 超过最大尝试次数后被重新领取'),
                true
            );
        }

        $this->logContext->set(LogContext::start(null, $event->correlationId(), [
            'event.id' => $delivery->eventId(),
            'event.type' => $event->eventType(),
            'delivery.id' => $delivery->id(),
            'delivery.handler' => $delivery->handlerKey(),
            'event.causation_id' => $event->causationId(),
        ]));

        $context = new DeliveryContext(
            $delivery->id(),
            $delivery->eventId(),
            $delivery->handlerKey(),
            $delivery->attemptCount(),
            $delivery->policyRevision(),
            $event->eventType(),
            $delivery->claimToken()
        );
        try {
            $registration->handler()->handle(
                $event,
                $context
            );
            if ($registration->queueName() === 'realtime' && $context->signalDecision() === null) {
                throw new \UnexpectedValueException('Realtime signal Handler 未记录投递决策');
            }
        } catch (SignalTransportUnavailable $exception) {
            return $this->defer($delivery, $exception);
        } catch (\Throwable $exception) {
            return $this->fail(
                $delivery,
                $registration,
                $exception,
                $delivery->attemptCount() >= $attemptLimit
            );
        } finally {
            $this->logContext->clear();
        }

        $completed = $this->deliveries->complete(
            $delivery->id(),
            $delivery->claimToken(),
            $this->clock->now(),
            $context->signalDecision()
        );

        return $completed ? DeliveryOutcome::COMPLETED : DeliveryOutcome::FENCED;
    }

    private function defer(ClaimedDelivery $delivery, SignalTransportUnavailable $exception): string
    {
        $now = $this->clock->now();
        $updated = $this->deliveries->defer(
            $delivery->id(),
            $delivery->claimToken(),
            new DeliveryFailure(false, $this->sanitizer->sanitize($exception), $now->modify('+30 seconds')),
            $now
        );
        return $updated ? DeliveryOutcome::RETRY_SCHEDULED : DeliveryOutcome::FENCED;
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
