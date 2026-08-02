<?php

namespace app\api\service\Auth;

interface CapabilityProvider
{
    public function forRoles(array $roleIds): array;
}
