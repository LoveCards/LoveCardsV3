<?php

declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Public;

use app\modules\Operations\SystemLifecycle\Application\MigrationPlanner;
use app\modules\Operations\SystemLifecycle\Application\SystemLifecycleCoordinator;
use app\modules\Operations\SystemLifecycle\Contract\MigrationSource;

final class Migrations
{
    public static function inspect(string $directory): array
    {
        $migrations = app(MigrationSource::class)->discover($directory);
        return app(MigrationPlanner::class)->plan($migrations)->toArray();
    }

    public static function run(string $directory): array
    {
        return app(SystemLifecycleCoordinator::class)->run($directory, false);
    }
}
