<?php

namespace app\modules\Platform\Auth\Public;

interface VisitorPolicy
{
    public function isEnabled(): bool;

    public function roleIds(): array;
}
