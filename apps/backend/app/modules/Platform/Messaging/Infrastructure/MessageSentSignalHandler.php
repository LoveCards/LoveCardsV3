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

/** 私信事实的主动更新投递；私信本身已由 PHP API 持久化。 */
final class MessageSentSignalHandler implements EventHandler
{
    public function handle(EventEnvelope $event, DeliveryContext $context): void
    {
        if ($event->eventType() !== 'message.sent' || $event->eventVersion() !== 1) {
            throw new \UnexpectedValueException('Realtime 私信事件类型不匹配');
        }
        $recipientId = (int) ($event->payload()['recipient_id'] ?? 0);
        if ($recipientId < 1) {
            throw new \UnexpectedValueException('Realtime 私信收件人无效');
        }
        $state = app(RuntimeCommunication::class)->signalDeliveryState();
        if ($state === 'skip') {
            $context->markSignalDecision('skipped');
            return;
        }
        if ($state !== 'deliver') throw new SignalTransportUnavailable('Realtime 信号链路不可用');
        if (!SignalPolicies::authorize('messages.new', $context)) {
            $context->markSignalDecision('skipped');
            return;
        }
        $context->assertSignalWindow();
        app(RealtimeSignalPublisher::class)->publish(new RealtimeSignal('messages', 'user:' . $recipientId));
        $context->markSignalDecision('applied');
    }
}
