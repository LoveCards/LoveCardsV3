<?php
declare(strict_types=1);

namespace app\modules\Platform\Messaging\Contract;

interface MessagePermission
{
    public function canSend(int $senderId, int $recipientId): bool;
}
