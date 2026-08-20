<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

final class MigrationStatus
{
    public const PENDING = 'pending';
    public const RUNNING = 'running';
    public const WAITING = 'waiting';
    public const FAILED = 'failed';
    public const COMPLETE = 'complete';

    public static function assertValid(string $status): void
    {
        if (!in_array($status, [self::PENDING, self::RUNNING, self::WAITING, self::FAILED, self::COMPLETE], true)) {
            throw new \InvalidArgumentException('Migration Status 不合法');
        }
    }
}
