<?php
declare(strict_types=1);

namespace app\modules\Platform\Files\Public;

use app\modules\Platform\Eventing\Public\HandlerRegistration;
use app\modules\Platform\Eventing\Public\ExecutionMode;

/** 本模块拥有的显式事件处理登记，供应用组合根装配。 */
final class EventHandlers
{
    /** @return HandlerRegistration[] */
    public static function registrations(): array
    {
        return [
            new HandlerRegistration(
                'file.purge.requested',
                1,
                'files.purge',
                'storage',
                ExecutionMode::DAEMON_REQUIRED,
                new \app\modules\Platform\Files\Infrastructure\PurgeFileHandler(),
                8,
                [5, 30, 300, 1800, 7200, 21600, 86400]
            ),
        ];
    }
}
