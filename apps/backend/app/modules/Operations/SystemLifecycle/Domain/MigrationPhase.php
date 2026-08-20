<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

final class MigrationPhase
{
    public const PREFLIGHT = 'preflight';
    public const EXPAND = 'expand';
    public const BACKFILL = 'backfill';
    public const VERIFY = 'verify';
    public const CONTRACT = 'contract';
    public const COMPLETE = 'complete';

    public static function assertValid(string $phase): void
    {
        if (!in_array($phase, self::all(), true)) {
            throw new \InvalidArgumentException('Migration Phase 不合法');
        }
    }

    /** @return string[] */
    public static function all(): array
    {
        return [self::PREFLIGHT, self::EXPAND, self::BACKFILL, self::VERIFY, self::CONTRACT, self::COMPLETE];
    }
}
