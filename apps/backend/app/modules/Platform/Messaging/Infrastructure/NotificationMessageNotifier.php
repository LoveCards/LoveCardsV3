<?php
declare(strict_types=1);

namespace app\modules\Platform\Messaging\Infrastructure;

use app\modules\Platform\Eventing\Public\DomainEventBus;
use app\modules\Platform\Messaging\Contract\MessageNotifier;

final class NotificationMessageNotifier implements MessageNotifier
{
    public function __construct(private DomainEventBus $events)
    {
    }

    public function messageSent(int $recipientId, int $senderId, int $conversationId, int $messageId): void
    {
        $this->events->publish(
            'message.sent',
            'message',
            (string) $messageId,
            [
                'recipient_id' => $recipientId,
                'sender_id' => $senderId,
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
            ],
            $senderId
        );
    }
}
