<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Contract;

use app\modules\Platform\Eventing\Public\RecordedEvent;

interface SystemNoticeIssuer
{
    public function issue(
        int $recipientId,
        string $noticeKey,
        string $type,
        string $message,
        ?string $actionUrl = null,
        string $severity = 'info',
        ?int $actorId = null
    ): RecordedEvent;
}
