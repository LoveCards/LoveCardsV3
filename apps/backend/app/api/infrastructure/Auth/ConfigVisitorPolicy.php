<?php

namespace app\api\infrastructure\Auth;

use app\api\service\Auth\VisitorPolicy;
use app\common\service\Config;

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
