<?php

namespace app\modules\Operations\Installer\Infrastructure;

use app\shared\SystemPaths\SystemPaths;

class Common
{
    public static function InstallLock(?SystemPaths $paths = null): bool
    {
        $paths = PersistentFile::paths($paths);
        PersistentFile::write($paths->stateRoot(), 'install.lock', "LoveCards.cn\n", 0600);
        return true;
    }

    public static function CheckInstallLock(?SystemPaths $paths = null): bool
    {
        $paths = PersistentFile::paths($paths);
        $stateRoot = $paths->stateRoot();
        if (is_link($stateRoot)) throw new \RuntimeException('实例状态目录不能是 symlink');
        $persistent = $stateRoot . DIRECTORY_SEPARATOR . 'install.lock';
        if (!is_link($persistent) && is_file($persistent)) return true;

        // Read-only compatibility for deployments installed before persistent
        // instance state existed. New installs never write into active code.
        $legacy = $paths->activeReleaseRoot() . DIRECTORY_SEPARATOR . 'lock.txt';
        return !is_link($legacy) && is_file($legacy);
    }
}
