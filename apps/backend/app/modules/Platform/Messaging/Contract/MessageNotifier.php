<?php
declare(strict_types=1);

namespace app\modules\Platform\Messaging\Contract;

interface MessageNotifier
{
    public function messageSent(int $recipientId, int $senderId, int $conversationId, int $messageId): void;
    public function messageCommitted(int $recipientId): void;
}
