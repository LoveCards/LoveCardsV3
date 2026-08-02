<?php

namespace app\modules\Platform\Rbac\Public;

use app\modules\Platform\Rbac\Service\RBAC;

final class Authorization
{
    public static function capabilities(array $roleIds): array { return RBAC::getUserCapabilities($roleIds); }
    public static function allCapabilities(): array { return RBAC::getAllCapabilities(); }
    public static function routeMeta(): array { return RBAC::getRouteMeta(); }
}
