<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Eventing\Contract\ClaimTokenGenerator;

final class SecureClaimTokenGenerator implements ClaimTokenGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}
