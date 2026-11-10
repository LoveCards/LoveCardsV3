<?php

declare(strict_types=1);

namespace app\modules\Platform\Messaging\Infrastructure;

use app\modules\Operations\Runtime\Public\RuntimeCommunication;
use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\EventHandler;
use app\modules\Platform\Eventing\Public\RealtimeSignal;
use app\modules\Platform\Eventing\Public\RealtimeSignalPublisher;
use app\modules\Platform\Eventing\Public\SignalPolicies;
use app\modules\Platform\Eventing\Public\SignalTransportUnavailable;
use app\modules\Platform\Messaging\Contract\MessagingRepository;

/** 已读游标变化只向会话成员发送刷新提示，不携带消息内容。 */
final class MessageReadSignalHandler implements EventHandler
{
    public function handle(EventEnvelope $event, DeliveryContext $context): void
    {
        if ($event->eventType() !== 'message.read' || $event->eventVersion() !== 1) {
            throw new \UnexpectedValueException('Realtime 已读事件类型不匹配');
        }
        $payload = $event->payload();
        $readerId = (int) ($payload['reader_id'] ?? 0);
        $conversationId = (int) ($payload['conversation_id'] ?? 0);
        $messageId = (int) ($payload['message_id'] ?? 0);
        if ($readerId < 1 || $conversationId < 1 || $messageId < 1) {
            throw new \UnexpectedValueException('Realtime 已读事件载荷无效');
        }
        if (!SignalPolicies::enabled('messages.read')) {
            $context->markSignalDecision('skipped');
            return;
        }
        $state = app(RuntimeCommunication::class)->signalDeliveryState();
        if ($state === 'skip') {
            $context->markSignalDecision('skipped');
            return;
        }
        if ($state !== 'deliver') throw new SignalTransportUnavailable('Realtime 信号链路不可用');
        $participants = app(MessagingRepository::class)->participantIds($conversationId);
        if (!in_array($readerId, $participants, true)) {
            throw new \UnexpectedValueException('Realtime 已读用户不是会话成员');
        }
        foreach ($participants as $recipientId) {
            app(RealtimeSignalPublisher::class)->publish(new RealtimeSignal('messages', 'user:' . $recipientId));
        }
        $context->markSignalDecision('applied');
    }
}
