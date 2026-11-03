<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\shared\SystemPaths\SystemPaths;
use app\shared\SystemPaths\SystemPathsResolver;

final class SystemPathsFactory
{
    public static function create(): SystemPaths
    {
        return (new SystemPathsResolver())->resolve(
            ['instance_root' => (string) config('lifecycle.instance_root')],
            rtrim(app()->getRootPath(), '/\\')
        );
    }
}
