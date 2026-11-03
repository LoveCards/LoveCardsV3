<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Public;

use app\modules\Platform\Eventing\Public\DomainEventBus;
use app\modules\Platform\Eventing\Public\RecordedEvent;
use app\modules\Platform\Notifications\Application\IssueSystemNotification;
use app\modules\Platform\Notifications\Contract\SystemNoticeIssuer;
use app\modules\Platform\Notifications\Domain\SystemNotice;
use app\shared\Transaction\TransactionManager;

/** 仅供可信后端服务调用；不得直接映射为普通客户端路由。 */
final class SystemNotifications implements SystemNoticeIssuer
{
    private IssueSystemNotification $issuer;

    public function __construct(DomainEventBus $events, TransactionManager $transactions)
    {
        $this->issuer = new IssueSystemNotification($events, $transactions);
    }

    public function issue(
        int $recipientId,
        string $noticeKey,
        string $type,
        string $message,
        ?string $actionUrl = null,
        string $severity = 'info',
        ?int $actorId = null
    ): RecordedEvent {
        return $this->issuer->issue(new SystemNotice(
            $recipientId,
            $noticeKey,
            $type,
            $message,
            $actionUrl,
            $severity,
            $actorId
        ));
    }
}
