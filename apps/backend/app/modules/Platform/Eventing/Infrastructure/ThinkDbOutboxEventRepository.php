<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Eventing\Contract\OutboxEventRepository;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use think\facade\Db;

final class ThinkDbOutboxEventRepository implements OutboxEventRepository
{
    public function append(EventEnvelope $event): int
    {
        return (int) Db::table('outbox_events')->insertGetId([
            'event_type' => $event->eventType(),
            'event_version' => $event->eventVersion(),
            'aggregate_type' => $event->aggregateType(),
            'aggregate_key' => $event->aggregateKey(),
            'actor_id' => $event->actorId(),
            'correlation_id' => $event->correlationId(),
            'causation_id' => $event->causationId(),
            'payload' => $event->payloadJson(),
            'occurred_at' => $event->occurredAt()->format('Y-m-d H:i:s.u'),
            'created_release_id' => $event->createdReleaseId(),
            'created_at' => $event->occurredAt()->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function findById(int $eventId): ?EventEnvelope
    {
        $row = Db::table('outbox_events')->where('id', $eventId)->find();
        return $row ? EventEnvelope::fromPersistence($row) : null;
    }
}
