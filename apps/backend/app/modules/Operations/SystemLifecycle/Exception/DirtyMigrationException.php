<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Exception;

final class DirtyMigrationException extends MigrationIntegrityException
{
    public static function forId(string $migrationId): self
    {
        return new self('已记录的 Migration Checksum 与代码不一致: ' . $migrationId);
    }
}
