<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Infrastructure;

use app\api\ApiException;
use app\modules\Content\Cards\Public\Cards;
use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\EventHandler;
use app\modules\Platform\Notifications\Public\Notifications;
use app\shared\Resource\ResourceRef;

/** 仅把公开卡片的收藏事实转换为作者通知。 */
final class LibrarySavedNotificationHandler implements EventHandler
{
    public function handle(EventEnvelope $event, DeliveryContext $context): void
    {
        if ($event->aggregateType() !== 'card') return;

        $payload = $event->payload();
        $actorId = (int) ($payload['actor_id'] ?? $payload['user_id'] ?? $event->actorId() ?? 0);
        $cardId = (int) ($payload['card_id'] ?? $event->aggregateKey());
        if ($actorId < 1 || $cardId < 1 || $event->aggregateKey() !== (string) $cardId) {
            throw new \UnexpectedValueException('library.saved payload 不完整或目标不一致');
        }

        $recipientId = (int) ($payload['owner_id'] ?? 0);
        if ($recipientId < 1) {
            try {
                $card = Cards::get($cardId);
            } catch (ApiException) {
                return;
            }
            $recipientId = (int) ($card['user_id'] ?? 0);
            if ($recipientId < 1) {
                throw new \UnexpectedValueException('library.saved 卡片缺少作者标识');
            }
        }
        if ($actorId === $recipientId) return;

        Notifications::direct(
            $recipientId,
            "save:card:{$cardId}:actor:{$actorId}",
            'library.saved',
            $actorId,
            new ResourceRef('card', (string) $cardId),
            ['card_id' => $cardId]
        , $context->policyScope());
    }
}
