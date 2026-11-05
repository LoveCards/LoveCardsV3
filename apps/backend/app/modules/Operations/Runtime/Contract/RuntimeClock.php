<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Contract;

/** 运行态时钟端口：快照时间戳统一 UTC。 */
interface RuntimeClock
{
    public function nowUtc(): \DateTimeImmutable;
}
