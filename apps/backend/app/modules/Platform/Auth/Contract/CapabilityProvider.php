<?php

namespace app\modules\Platform\Auth\Contract;

use app\shared\Authorization\GrantSet;

interface CapabilityProvider
{
    public function forRoles(array $roleIds): GrantSet;
}
