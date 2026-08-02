<?php

namespace app\modules\Platform\Auth\Contract;

interface CapabilityProvider
{
    public function forRoles(array $roleIds): array;
}
