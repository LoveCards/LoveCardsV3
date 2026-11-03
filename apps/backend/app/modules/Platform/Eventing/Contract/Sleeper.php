<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Contract;

interface Sleeper
{
    public function sleepMilliseconds(int $milliseconds): void;
}
