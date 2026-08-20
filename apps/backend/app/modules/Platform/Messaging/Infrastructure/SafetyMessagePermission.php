<?php
declare(strict_types=1);

namespace app\modules\Platform\Messaging\Infrastructure;

use app\modules\Platform\Messaging\Contract\MessagePermission;
use app\modules\Platform\Safety\Public\Safety;

final class SafetyMessagePermission implements MessagePermission
{
    public function canSend(int $senderId, int $recipientId): bool
    {
        return Safety::canMessage($senderId, $recipientId);
    }
}
