<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

final class ExecutionMode
{
    public const REQUEST_SAFE = 'request_safe';
    public const DAEMON_REQUIRED = 'daemon_required';

    public static function assert(string $mode): void
    {
        if (!in_array($mode, [self::REQUEST_SAFE, self::DAEMON_REQUIRED], true)) {
            throw new \InvalidArgumentException("未知 Event Handler 执行模式：{$mode}");
        }
    }
}
