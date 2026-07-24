<?php

namespace app\api\application\Auth;

interface VisitorPolicy
{
    public function isEnabled(): bool;

    public function roleIds(): array;
}
