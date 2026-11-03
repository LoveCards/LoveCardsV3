<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Contract\ReleasePackageStore;
use app\modules\Operations\SystemLifecycle\Domain\StoredReleasePackage;
use app\shared\SystemPaths\SystemPaths;

final class LocalReleasePackageStore implements ReleasePackageStore
{
    private ReleaseArchiveLimits $limits;

    public function __construct(private SystemPaths $paths, ?ReleaseArchiveLimits $limits = null)
    {
        $this->limits = $limits ?? ReleaseArchiveLimits::configured();
    }

    public function store(string $planId, string $sourcePath): StoredReleasePackage
    {
        $this->assertPlanId($planId);
        if (!is_file($sourcePath) || is_link($sourcePath) || !is_readable($sourcePath)) {
            throw new \RuntimeException('待升级 Release ZIP 不存在、是 symlink 或不可读');
        }

        $root = $this->planRoot($planId);
        $this->ensureRoot($root);
        $destination = $root . DIRECTORY_SEPARATOR . 'package.zip';
        if (file_exists($destination) || is_link($destination)) {
            return $this->existing($destination);
        }

        $temporary = $destination . '.part-' . bin2hex(random_bytes(8));
        $source = fopen($sourcePath, 'rb');
        $target = fopen($temporary, 'xb');
        if ($source === false || $target === false) {
            if (is_resource($source)) fclose($source);
            if (is_resource($target)) fclose($target);
            @unlink($temporary);
            throw new \RuntimeException('无法创建持久化 Release Package');
        }

        $hash = hash_init('sha256');
        $size = 0;
        try {
            while (!feof($source)) {
                $chunk = fread($source, 1048576);
                if ($chunk === false) throw new \RuntimeException('读取 Release Package 失败');
                if ($chunk === '') continue;
                $length = strlen($chunk);
                if ($size > $this->limits->zipBytes() - $length) {
                    throw new \RuntimeException('Release ZIP 大小超出限制');
                }
                $this->writeAll($target, $chunk);
                hash_update($hash, $chunk);
                $size += $length;
            }
            if ($size < 1 || !fflush($target)) throw new \RuntimeException('Release Package 为空或无法落盘');
            if (function_exists('fsync') && !fsync($target)) throw new \RuntimeException('Release Package 无法同步到磁盘');
        } catch (\Throwable $exception) {
            fclose($target);
            fclose($source);
            @unlink($temporary);
            throw $exception;
        }
        fclose($target);
        fclose($source);

        if (!@chmod($temporary, 0400) && DIRECTORY_SEPARATOR !== '\\') {
            @unlink($temporary);
            throw new \RuntimeException('无法限制 Release Package 权限');
        }
        if (!rename($temporary, $destination)) {
            @chmod($temporary, 0600);
            @unlink($temporary);
            if (is_file($destination) && !is_link($destination)) return $this->existing($destination);
            throw new \RuntimeException('无法提交持久化 Release Package');
        }

        return new StoredReleasePackage($destination, hash_final($hash), $size);
    }

    public function remove(string $planId): void
    {
        $this->assertPlanId($planId);
        $root = $this->planRoot($planId);
        if (is_link($root)) throw new \RuntimeException('Upgrade Plan 目录不能是 symlink');
        if (!is_dir($root)) return;
        $file = $root . DIRECTORY_SEPARATOR . 'package.zip';
        if (is_file($file) && !is_link($file)) {
            @chmod($file, 0600);
            if (!unlink($file)) throw new \RuntimeException('无法清理 Release Package');
        }
        foreach (glob($root . DIRECTORY_SEPARATOR . 'package.zip.part-*') ?: [] as $part) {
            if (is_file($part) && !is_link($part)) @unlink($part);
        }
        if (!rmdir($root)) throw new \RuntimeException('无法清理 Upgrade Plan 目录');
    }

    private function existing(string $path): StoredReleasePackage
    {
        $checksum = hash_file('sha256', $path);
        $size = filesize($path);
        if ($checksum === false || $size === false || $size < 1 || $size > $this->limits->zipBytes()) {
            throw new \RuntimeException('现有 Release Package 无法校验');
        }
        return new StoredReleasePackage($path, $checksum, (int) $size);
    }

    private function ensureRoot(string $root): void
    {
        $upgradeRoot = $this->paths->upgradeRoot();
        if (!is_dir($upgradeRoot) && !mkdir($upgradeRoot, 0700, true) && !is_dir($upgradeRoot)) {
            throw new \RuntimeException('无法创建 Upgrade Package 根目录');
        }
        if (is_link($upgradeRoot)) throw new \RuntimeException('Upgrade Package 根目录不能是 symlink');
        if (!is_dir($root) && !mkdir($root, 0700) && !is_dir($root)) {
            throw new \RuntimeException('无法创建 Upgrade Plan 目录');
        }
        if (is_link($root)) throw new \RuntimeException('Upgrade Plan 目录不能是 symlink');
    }

    private function planRoot(string $planId): string
    {
        return $this->paths->upgradeRoot() . DIRECTORY_SEPARATOR . $planId;
    }

    private function assertPlanId(string $planId): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $planId) !== 1) {
            throw new \InvalidArgumentException('Upgrade Plan ID 不合法');
        }
    }

    /** @param resource $handle */
    private function writeAll($handle, string $value): void
    {
        $offset = 0;
        $length = strlen($value);
        while ($offset < $length) {
            $written = fwrite($handle, substr($value, $offset));
            if ($written === false || $written === 0) throw new \RuntimeException('写入 Release Package 失败');
            $offset += $written;
        }
    }
}
