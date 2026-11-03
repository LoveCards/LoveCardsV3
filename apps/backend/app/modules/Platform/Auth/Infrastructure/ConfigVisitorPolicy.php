<?php

namespace app\modules\Platform\Auth\Infrastructure;

use app\modules\Platform\Auth\Public\VisitorPolicy;
use app\modules\Platform\Config\Public\Config;

class ConfigVisitorPolicy implements VisitorPolicy
{
    public function isEnabled(): bool
    {
        return (bool) Config::get('core.visitor_mode');
    }

    public function roleIds(): array
    {
        return [config('system.system_roles.guest')];
    }
}
