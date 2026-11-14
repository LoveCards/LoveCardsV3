<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

use app\shared\Observability\LogContextProvider;
use think\facade\Db;

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

    /** 信号续批复用父投递资格；控制锁先于父投递锁，后续登记不重新获取资格。 */
    public function publishSignalContinuation(DeliveryContext $parent, string $signalKey, string $type, string $aggregateType, string $aggregateKey, array $payload, ?int $actorId = null): ?RecordedEvent
    {
        return SignalPolicies::continueSignal($signalKey, $parent, function (array $origin) use ($type, $aggregateType, $aggregateKey, $payload, $actorId): ?RecordedEvent {
            $exists = Db::table('outbox_events')->where('event_type', $type)->where('aggregate_key', $aggregateKey)->find();
            if ($exists) return null;
            $correlationId = $this->context->current()->correlationId() ?: bin2hex(random_bytes(16));
            return $this->recorder->record(new EventEnvelope(
                $type, 1, $aggregateType, $aggregateKey, $payload,
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')), $actorId, $correlationId
            ), $origin);
        });
    }
}
