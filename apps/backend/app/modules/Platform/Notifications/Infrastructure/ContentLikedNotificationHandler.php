<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\EventHandler;
use app\modules\Platform\Notifications\Public\Notifications;
use app\shared\Resource\ResourceRef;

/** 将已落入 Outbox 的点赞事实转换为作者通知。 */
final class ContentLikedNotificationHandler implements EventHandler
{
    public function handle(EventEnvelope $event, DeliveryContext $context): void
    {
        $payload = $event->payload();
        $targetType = (string) ($payload['target_type'] ?? '');
        $targetId = (int) ($payload['target_id'] ?? 0);
        $actorId = (int) ($payload['actor_id'] ?? $event->actorId() ?? 0);
        $recipientId = (int) ($payload['owner_id'] ?? 0);
        $cardId = (int) ($payload['card_id'] ?? 0);
        $commentId = isset($payload['comment_id']) ? (int) $payload['comment_id'] : null;

        if (!in_array($targetType, ['card', 'comment'], true)
            || $targetId < 1
            || $actorId < 1
            || $recipientId < 1
            || $cardId < 1
            || $event->aggregateType() !== $targetType
            || $event->aggregateKey() !== (string) $targetId
            || ($targetType === 'card' && $targetId !== $cardId)
            || ($targetType === 'comment' && ($commentId === null || $commentId < 1 || $commentId !== $targetId))
        ) {
            throw new \UnexpectedValueException('content.liked payload 不完整或目标不一致');
        }
        if ($actorId === $recipientId) return;

        $data = ['card_id' => $cardId];
        if ($commentId !== null) $data['comment_id'] = $commentId;

        Notifications::direct(
            $recipientId,
            "like:{$targetType}:{$targetId}:actor:{$actorId}",
            $targetType === 'card' ? 'content.card_liked' : 'content.comment_liked',
            $actorId,
            new ResourceRef($targetType, (string) $targetId),
            $data
        );
    }
}
