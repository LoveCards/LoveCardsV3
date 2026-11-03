<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Contract;

interface FailureSanitizer
{
    public function sanitize(\Throwable $exception): string;
}
