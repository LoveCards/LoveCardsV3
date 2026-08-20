<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

use app\shared\Observability\LogContextProvider;

/** Small application-facing publisher; transport and delivery remain infrastructure concerns. */
final class DomainEventBus
{
    public function __construct(private EventRecorder $recorder, private LogContextProvider $context) {}

    public function publish(string $type, string $aggregateType, string $aggregateKey, array $payload, ?int $actorId = null): RecordedEvent
    {
        $correlationId = $this->context->current()->correlationId() ?: bin2hex(random_bytes(16));
        return $this->recorder->record(new EventEnvelope(
            $type, 1, $aggregateType, $aggregateKey, $payload,
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')), $actorId, $correlationId
        ));
    }
}
