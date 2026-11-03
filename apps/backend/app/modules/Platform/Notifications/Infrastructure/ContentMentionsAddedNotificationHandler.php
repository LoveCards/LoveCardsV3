<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\EventHandler;
use app\modules\Platform\Notifications\Public\Notifications;
use app\shared\Resource\ResourceRef;
use think\facade\Db;

/** Projects newly persisted card/comment mention relationships. */
final class ContentMentionsAddedNotificationHandler implements EventHandler
{
    public function handle(EventEnvelope $event, DeliveryContext $context): void
    {
        $payload = $event->payload();
        $resourceType = (string) ($payload['resource_type'] ?? '');
        $resourceId = (int) ($payload['resource_id'] ?? 0);
        $cardId = (int) ($payload['card_id'] ?? 0);
        $commentId = isset($payload['comment_id']) ? (int) $payload['comment_id'] : null;
        $actorId = (int) ($payload['actor_id'] ?? 0);
        if (!in_array($resourceType, ['card', 'comment'], true)
            || $resourceId < 1 || $cardId < 1 || $actorId < 1
            || ($resourceType === 'comment' && ($commentId ?? 0) < 1)) {
            throw new \UnexpectedValueException('content.mentions.added payload 不完整');
        }

        $suppressed = array_fill_keys(array_map('intval', is_array($payload['suppressed_recipient_ids'] ?? null)
            ? $payload['suppressed_recipient_ids'] : []), true);
        $mentions = [];
        foreach (is_array($payload['mentions'] ?? null) ? $payload['mentions'] : [] as $row) {
            if (!is_array($row)) continue;
            $userId = (int) ($row['user_id'] ?? 0);
            $username = trim((string) ($row['username'] ?? ''));
            if ($userId > 0 && $username !== '') $mentions[$userId] = $username;
        }
        if ($mentions === []) return;

        $active = array_fill_keys(Db::table('users')
            ->whereIn('id', array_keys($mentions))
            ->where('status', 0)
            ->whereNull('deleted_at')
            ->column('id'), true);
        $resource = new ResourceRef($resourceType, (string) $resourceId);
        foreach ($mentions as $recipientId => $username) {
            if ($recipientId === $actorId || isset($suppressed[$recipientId]) || !isset($active[$recipientId])) continue;
            Notifications::direct(
                $recipientId,
                "mention:{$resourceType}:{$resourceId}:user:{$recipientId}",
                'content.mentioned',
                $actorId,
                $resource,
                [
                    'card_id' => $cardId,
                    'comment_id' => $commentId,
                    'mentioned_username' => $username,
                ]
            );
        }
    }
}
