<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Contract\RuntimeClock;

final class SystemRuntimeClock implements RuntimeClock
{
    public function nowUtc(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
