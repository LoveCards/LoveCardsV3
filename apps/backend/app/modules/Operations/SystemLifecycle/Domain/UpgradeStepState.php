<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

final class UpgradeStepState
{
    public const PENDING = 'pending';
    public const RUNNING = 'running';
    public const WAITING = 'waiting';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const SKIPPED = 'skipped';

    public static function assertValid(string $state): void
    {
        if (!in_array($state, [self::PENDING, self::RUNNING, self::WAITING, self::SUCCEEDED, self::FAILED, self::SKIPPED], true)) {
            throw new \InvalidArgumentException('Upgrade Step State 不合法');
        }
    }

    public static function isTerminal(string $state): bool
    {
        return in_array($state, [self::SUCCEEDED, self::SKIPPED], true);
    }
}
