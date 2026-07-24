<?php

namespace app\api\application\Auth;

interface CapabilityProvider
{
    public function forRoles(array $roleIds): array;
}
