<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\EventHandler;
use app\modules\Platform\Notifications\Domain\SystemNotice;
use app\modules\Platform\Notifications\Public\Notifications;

/** 将可信服务发布的系统通知事实投影为收件人站内通知。 */
final class SystemNotificationIssuedHandler implements EventHandler
{
    public function handle(EventEnvelope $event, DeliveryContext $context): void
    {
        if ($event->eventType() !== 'system.notification.issued' || $event->eventVersion() !== 1) {
            throw new \UnexpectedValueException('系统通知事件类型或版本无效');
        }
        if ($event->aggregateType() !== 'user') {
            throw new \UnexpectedValueException('系统通知 aggregate_type 必须是 user');
        }

        $notice = SystemNotice::fromEventPayload($event->payload(), $event->actorId());
        if ($event->aggregateKey() !== (string) $notice->recipientId()) {
            throw new \UnexpectedValueException('系统通知收件人与 aggregate_key 不一致');
        }

        app(\app\shared\Transaction\TransactionManager::class)->run(function () use ($notice, $context): void {
            $batches = app(\app\modules\Platform\Notifications\Contract\SystemNoticeBatchRepository::class);
            $projection = $batches->lockProjection($context->eventId());
            if ($projection !== null && $projection['projected_at'] !== null) return;
            // 冻结后账号变成访客或被注销时跳过；不能因读取快照延长其收件资格。
            $eligible = $projection === null || \app\modules\Platform\User\Public\NotificationRecipients::eligible($notice->recipientId());
            $notificationId = $eligible ? Notifications::create($notice->notificationIntent()) : 0;
            $batches->completeProjection($context->eventId(), $notificationId);
        });
    }
}
