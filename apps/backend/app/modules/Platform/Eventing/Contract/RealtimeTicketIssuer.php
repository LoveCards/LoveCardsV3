<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Contract;

interface RealtimeTicketIssuer
{
    /**
     * @param string[] $topics
     * @return array{ticket:string,endpoint:string,protocol:string,expires_at:string,topics:string[],cursor_strategy:string}
     */
    public function issue(int $recipientId, array $topics = ['notifications', 'messages']): array;
}
