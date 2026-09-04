<?php
declare(strict_types=1);

namespace app\modules\Platform\Messaging\Infrastructure;

use app\api\ApiException;
use app\modules\Platform\Messaging\Contract\MessagePermission;
use app\modules\Platform\Safety\Public\Safety;

final class SafetyMessagePermission implements MessagePermission
{
    public function canSend(int $senderId, int $recipientId): bool
    {
        if (Safety::isBlockedEitherDirection($senderId, $recipientId)) {
            throw ApiException::forbidden('屏蔽关系下无法发送私信', ApiException::CODE_PERMISSION_DENIED, ['reason_code' => 'blocked_relationship']);
        }
        return Safety::canMessage($senderId, $recipientId);
    }
}
