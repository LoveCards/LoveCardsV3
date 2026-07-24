<?php

namespace app\api\infrastructure\Auth;

use app\api\application\Auth\CapabilityProvider;
use app\api\service\Rbac\RBAC;

class RbacCapabilityProvider implements CapabilityProvider
{
    public function forRoles(array $roleIds): array
    {
        return RBAC::getUserCapabilities($roleIds);
    }
}
