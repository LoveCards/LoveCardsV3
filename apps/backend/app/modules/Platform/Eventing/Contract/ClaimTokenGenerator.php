<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Contract;

interface ClaimTokenGenerator
{
    public function generate(): string;
}
