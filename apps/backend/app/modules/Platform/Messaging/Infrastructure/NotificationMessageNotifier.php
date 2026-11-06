<?php
declare(strict_types=1);

namespace app\modules\Platform\Messaging\Infrastructure;

use app\modules\Platform\Eventing\Public\DomainEventBus;
use app\modules\Platform\Eventing\Public\RealtimeSignal;
use app\modules\Platform\Eventing\Public\RealtimeSignalPublisher;
use app\modules\Platform\Messaging\Contract\MessageNotifier;

final class NotificationMessageNotifier implements MessageNotifier
{
    public function __construct(private DomainEventBus $events, private ?RealtimeSignalPublisher $signals = null)
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
        // 消息实时信号独立于站内通知策略；通知被关闭时，私信仍需刷新。
        $this->signals?->publish(new RealtimeSignal('messages', 'user:' . $recipientId, ''));
    }
}


