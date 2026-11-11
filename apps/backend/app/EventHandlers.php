<?php
declare(strict_types=1);

namespace app;

use app\modules\Platform\Eventing\Public\HandlerRegistry;

/** 应用组合根：所有执行入口共用此显式装配，不查询数据库。 */
final class EventHandlers
{
    public static function create(): HandlerRegistry
    {
        return new HandlerRegistry([
            ...\app\modules\Platform\Notifications\Public\EventHandlers::registrations(),
            ...\app\modules\Platform\Messaging\Public\EventHandlers::registrations(),
            ...\app\modules\Content\Cards\Public\EventHandlers::registrations(),
            ...\app\modules\Platform\Files\Public\EventHandlers::registrations(),
        ]);
    }
}
