<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Contract;

interface Clock
{
    public function now(): \DateTimeImmutable;

    public function monotonicMilliseconds(): int;
}
