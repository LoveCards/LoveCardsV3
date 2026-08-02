<?php

namespace app\modules\Platform\Auth\Infrastructure;

use app\modules\Platform\Auth\Contract\CapabilityProvider;
use app\modules\Platform\Rbac\Public\Authorization;

class RbacCapabilityProvider implements CapabilityProvider
{
    public function forRoles(array $roleIds): array
    {
        return Authorization::capabilities($roleIds);
    }
}
