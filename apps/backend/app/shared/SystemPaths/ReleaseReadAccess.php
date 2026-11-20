<?php
declare(strict_types=1);

namespace app\shared\SystemPaths;

/** 将不可变 Release 和活动指针交给 Web 进程所在的只读组。 */
final class ReleaseReadAccess
{
    public function __construct(private SystemPaths $paths) {}

    public function prepareInstanceRoot(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') return;
        $root = $this->paths->instanceRoot();
        if (is_link($root) || !is_dir($root)) throw new \RuntimeException('实例目录不可用于 Release 读取');
        $this->prepareDirectory($root, 0010);
    }

    public function prepareDirectory(string $path, int $groupPermissions): void
    {
        if (DIRECTORY_SEPARATOR === '\\') return;
        if (is_link($path) || !is_dir($path)) throw new \RuntimeException('Release 读取目录不合法');
        $this->apply($path);
        $mode = fileperms($path);
        if ($mode === false || !@chmod($path, (($mode & 07777) & ~0022) | $groupPermissions)) {
            throw new \RuntimeException('无法设置 Release 读取目录权限');
        }
    }

    public function apply(string $path): void
    {
        if (DIRECTORY_SEPARATOR === '\\') return;
        if (is_link($path) || !file_exists($path)) {
            throw new \RuntimeException('Release 读取权限目标不存在或为 symlink');
        }

        $raw = getenv('LC_RELEASE_READ_GROUP');
        $configured = is_string($raw) ? trim($raw) : '';
        if ($configured !== '') {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $configured) !== 1
                && preg_match('/^[0-9]+$/', $configured) !== 1) {
                throw new \RuntimeException('LC_RELEASE_READ_GROUP 不合法');
            }
            $group = ctype_digit($configured) ? (int) $configured : $configured;
        } else {
            $instanceRoot = $this->paths->instanceRoot();
            if (is_link($instanceRoot) || !is_dir($instanceRoot)) {
                throw new \RuntimeException('实例目录不可用于确定 Release 读取组');
            }
            $group = filegroup($instanceRoot);
            if ($group === false) throw new \RuntimeException('无法确定 Release 读取组');
        }

        if (!@chgrp($path, $group)) {
            throw new \RuntimeException('无法设置 Release 读取组');
        }
    }
}
