<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Eventing\Contract\Sleeper;

final class NativeSleeper implements Sleeper
{
    public function sleepMilliseconds(int $milliseconds): void
    {
        if ($milliseconds < 0) {
            throw new \InvalidArgumentException('sleep milliseconds 不能为负数');
        }
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
