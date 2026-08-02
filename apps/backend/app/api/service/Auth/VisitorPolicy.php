<?php

namespace app\api\service\Auth;

interface VisitorPolicy
{
    public function isEnabled(): bool;

    public function roleIds(): array;
}
