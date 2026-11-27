<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Public;

use app\modules\Platform\Notifications\Service\AdminNotificationQueries;

/** 管理端通知元数据及运维统计的统一读取入口。 */
final class AdminNotifications
{
    public static function stats(): array
    {
        return app(AdminNotificationQueries::class)->stats();
    }
}
