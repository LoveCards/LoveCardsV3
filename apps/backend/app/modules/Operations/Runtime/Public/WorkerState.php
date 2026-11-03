<?php

declare(strict_types=1);

namespace app\modules\Operations\Runtime\Public;

final class WorkerState
{
    public const STARTING = 'starting';
    public const RUNNING = 'running';
    public const STOPPING = 'stopping';
    public const STOPPED = 'stopped';

    public static function assert(string $state): void
    {
        if (!in_array($state, [self::STARTING, self::RUNNING, self::STOPPING, self::STOPPED], true)) {
            throw new \InvalidArgumentException("未知 Worker 状态：{$state}");
        }
    }
}
