<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Contract;

use app\modules\Platform\Notifications\Domain\NotificationIntent;
use app\modules\Platform\Notifications\Domain\NotificationWriteReceipt;

/** 在保持既有 create(): int 契约的同时，提供首次写入判定。 */
interface NotificationReceiptRepository extends NotificationRepository
{
    public function createWithReceipt(NotificationIntent $intent): NotificationWriteReceipt;
}
