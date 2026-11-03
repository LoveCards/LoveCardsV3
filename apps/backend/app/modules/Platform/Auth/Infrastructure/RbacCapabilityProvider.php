<?php

namespace app\modules\Platform\Auth\Infrastructure;

use app\modules\Platform\Auth\Contract\CapabilityProvider;
use app\modules\Platform\Rbac\Public\Authorization;
use app\shared\Authorization\GrantSet;

class RbacCapabilityProvider implements CapabilityProvider
{
    public function forRoles(array $roleIds): GrantSet
    {
        return Authorization::grants($roleIds);
    }
}
