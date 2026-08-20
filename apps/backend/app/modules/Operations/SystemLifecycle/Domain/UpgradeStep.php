<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

final class UpgradeStep
{
    public const ENVIRONMENT_PROBE = 'environment_probe';
    public const IMPORT_RELEASE = 'import_release';
    public const VERIFY_RELEASE = 'verify_release';
    public const STAGE_RELEASE = 'stage_release';
    public const SCHEMA_CHECK = 'schema_check';
    public const PREFLIGHT_MIGRATIONS = 'preflight_migrations';
    public const EXPAND_MIGRATIONS = 'expand_migrations';
    public const BACKFILL = 'backfill';
    public const VERIFY_MIGRATIONS = 'verify_migrations';
    public const BACKUP_GATE = 'backup_gate';
    public const DRAIN_RUNTIME = 'drain_runtime';
    public const ACTIVATE_RELEASE = 'activate_release';
    public const RELOAD_RUNTIME = 'reload_runtime';
    public const HEALTH_CHECK = 'health_check';
    public const CONTRACT_MIGRATIONS = 'contract_migrations';

    /** @return string[] */
    public static function ordered(): array
    {
        return [
            self::ENVIRONMENT_PROBE,
            self::IMPORT_RELEASE,
            self::VERIFY_RELEASE,
            self::STAGE_RELEASE,
            self::SCHEMA_CHECK,
            // The backup fence must precede every migration phase. Even a
            // migration marked `preflight` may contain guarded SQL in legacy
            // packages; placing the gate here makes the safety invariant
            // independent of the Release author's phase annotations.
            self::BACKUP_GATE,
            self::PREFLIGHT_MIGRATIONS,
            self::EXPAND_MIGRATIONS,
            self::BACKFILL,
            self::VERIFY_MIGRATIONS,
            self::DRAIN_RUNTIME,
            self::ACTIVATE_RELEASE,
            self::RELOAD_RUNTIME,
            self::HEALTH_CHECK,
            self::CONTRACT_MIGRATIONS,
        ];
    }

    public static function assertValid(string $step): void
    {
        if (!in_array($step, self::ordered(), true)) throw new \InvalidArgumentException('Upgrade Step 不合法');
    }

    public static function isOptional(string $step): bool
    {
        self::assertValid($step);
        return in_array($step, [self::DRAIN_RUNTIME, self::RELOAD_RUNTIME, self::CONTRACT_MIGRATIONS], true);
    }
}
