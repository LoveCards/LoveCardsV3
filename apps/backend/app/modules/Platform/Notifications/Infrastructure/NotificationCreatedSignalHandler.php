<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Operations\Runtime\Public\RuntimeCommunication;
use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\EventHandler;
use app\modules\Platform\Eventing\Public\RealtimeSignal;
use app\modules\Platform\Eventing\Public\RealtimeSignalPublisher;
use app\modules\Platform\Eventing\Public\SignalPolicies;
use app\modules\Platform\Eventing\Public\SignalTransportUnavailable;
use app\modules\Platform\Notifications\Domain\NotificationCursor;

/** 通知已持久化后的独立主动更新投递。 */
final class NotificationCreatedSignalHandler implements EventHandler
{
    public function handle(EventEnvelope $event, DeliveryContext $context): void
    {
        if ($event->eventType() !== 'notification.created' || $event->eventVersion() !== 1) {
            throw new \UnexpectedValueException('Realtime 通知事件类型不匹配');
        }
        $payload = $event->payload();
        $recipientId = (int) ($payload['recipient_id'] ?? 0);
        $notificationId = (int) ($payload['notification_id'] ?? 0);
        $createdAt = $payload['created_at'] ?? null;
        if ($recipientId < 1 || $notificationId < 1 || !is_string($createdAt) || $createdAt === '') {
            throw new \UnexpectedValueException('Realtime 通知事件载荷无效');
        }
        if (!SignalPolicies::enabled('notifications.new')) {
            $context->markSignalDecision('skipped');
            return;
        }
        $state = app(RuntimeCommunication::class)->signalDeliveryState();
        if ($state === 'skip') {
            $context->markSignalDecision('skipped');
            return;
        }
        if ($state !== 'deliver') throw new SignalTransportUnavailable('Realtime 信号链路不可用');
        $cursor = NotificationCursor::encode($createdAt, $notificationId);
        app(RealtimeSignalPublisher::class)->publish(new RealtimeSignal('notifications', 'user:' . $recipientId, $cursor));
        $context->markSignalDecision('applied');
    }
}
