<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\EventHandler;
use app\modules\Platform\Notifications\Public\Notifications;
use app\shared\Resource\ResourceRef;

/** Creates the recipient notification after a follow event is durably recorded. */
final class SocialFollowedNotificationHandler implements EventHandler
{
    public function handle(EventEnvelope $event, DeliveryContext $context): void
    {
        $recipientId = (int) $event->aggregateKey();
        $actorId = isset($event->payload()['actor_id']) ? (int) $event->payload()['actor_id'] : null;
        if ($recipientId < 1 || $actorId === null || $actorId < 1 || $recipientId === $actorId) {
            return;
        }

        Notifications::direct(
            $recipientId,
            'follow:' . $actorId . ':' . $recipientId,
            'social.followed',
            $actorId,
            new ResourceRef('user', (string) $actorId)
        );
    }
}
