<?php

namespace app\modules\Operations\Installer\Infrastructure;

use app\shared\SystemPaths\SystemPaths;
use app\modules\Platform\Config\Public\DeploymentNamespace;
use think\facade\Env;

class Common
{
    public static function InstallLock(?SystemPaths $paths = null): bool
    {
        $paths = PersistentFile::paths($paths);
        if (self::CheckInstallLock($paths)) throw new \RuntimeException('系统已安装，禁止覆盖安装锁');
        $native = getenv('LC_DEPLOYMENT_ID');
        $configured = $native !== false ? $native : (function_exists('app') ? Env::get('LC_DEPLOYMENT_ID', '') : '');
        $id = is_string($configured) && $configured !== '' ? $configured : bin2hex(random_bytes(16));
        DeploymentNamespace::fromId($id);
        PersistentFile::writeOnce($paths->stateRoot(), 'install.lock', "LoveCards.cn\nLC_DEPLOYMENT_ID={$id}\n", 0600);
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
