<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\EventHandler;
use app\modules\Platform\Notifications\Public\Notifications;
use app\shared\Resource\ResourceRef;

/** 将已可靠记录的私信事件转换为收件人的站内通知。 */
final class MessageSentNotificationHandler implements EventHandler
{
    public function handle(EventEnvelope $event, DeliveryContext $context): void
    {
        $payload = $event->payload();
        $recipientId = (int) ($payload['recipient_id'] ?? 0);
        $senderId = (int) ($payload['sender_id'] ?? 0);
        $conversationId = (int) ($payload['conversation_id'] ?? 0);
        $messageId = (int) ($payload['message_id'] ?? 0);
        if ($recipientId < 1 || $senderId < 1 || $conversationId < 1 || $messageId < 1) {
            throw new \UnexpectedValueException('message.sent payload 不完整');
        }

        Notifications::direct(
            $recipientId,
            'message:' . $messageId,
            'message',
            $senderId,
            new ResourceRef('message', (string) $messageId),
            ['conversation_id' => $conversationId, 'message_id' => $messageId]
        );
    }
}
