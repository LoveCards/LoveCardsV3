<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Application;

use app\modules\Platform\Eventing\Public\DomainEventBus;
use app\modules\Platform\Eventing\Public\RecordedEvent;
use app\modules\Platform\Notifications\Domain\SystemNotice;
use app\shared\Transaction\TransactionManager;

final class IssueSystemNotification
{
    public function __construct(
        private DomainEventBus $events,
        private TransactionManager $transactions
    ) {}

    public function issue(SystemNotice $notice): RecordedEvent
    {
        return $this->transactions->run(fn(): RecordedEvent => $this->events->publish(
            'system.notification.issued',
            'user',
            (string) $notice->recipientId(),
            $notice->eventPayload(),
            $notice->actorId()
        ));
    }
}
