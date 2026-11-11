<?php
declare(strict_types=1);

namespace app\modules\Operations\Installer\Infrastructure;

use app\shared\SystemPaths\SystemPaths;

/** Small atomic writer for installer-owned persistent state. */
final class PersistentFile
{
    public static function paths(?SystemPaths $paths = null): SystemPaths
    {
        if ($paths !== null) return $paths;
        $resolved = app(SystemPaths::class);
        if (!$resolved instanceof SystemPaths) throw new \RuntimeException('无法解析实例持久目录');
        return $resolved;
    }

    public static function write(string $directory, string $filename, string $contents, int $mode = 0600): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $filename) !== 1) {
            throw new \InvalidArgumentException('持久文件名不合法');
        }
        self::ensureDirectory($directory);
        $path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            throw new \RuntimeException('持久配置目标不是安全的普通文件');
        }
        $temporary = $path . '.next.' . bin2hex(random_bytes(8));
        $handle = fopen($temporary, 'xb');
        if ($handle === false) throw new \RuntimeException('无法创建持久配置临时文件');
        try {
            $offset = 0;
            $length = strlen($contents);
            while ($offset < $length) {
                $written = fwrite($handle, substr($contents, $offset));
                if ($written === false || $written === 0) throw new \RuntimeException('持久配置写入失败');
                $offset += $written;
            }
            if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
                throw new \RuntimeException('持久配置无法同步到磁盘');
            }
        } finally {
            fclose($handle);
        }
        @chmod($temporary, $mode);
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('持久配置无法原子替换');
        }
        return $path;
    }

    /** 安装锁只能创建一次；先完整落盘临时文件，再原子发布，避免并发请求覆盖身份。 */
    public static function writeOnce(string $directory, string $filename, string $contents, int $mode = 0600): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $filename) !== 1) {
            throw new \InvalidArgumentException('持久文件名不合法');
        }
        self::ensureDirectory($directory);
        $path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (is_link($path) || file_exists($path)) throw new \RuntimeException('安装锁已存在，禁止覆盖');
        $temporary = $path . '.next.' . bin2hex(random_bytes(8));
        try {
            $handle = fopen($temporary, 'xb');
            if ($handle === false) throw new \RuntimeException('无法创建安装锁临时文件');
            try {
                $offset = 0;
                while ($offset < strlen($contents)) {
                    $written = fwrite($handle, substr($contents, $offset));
                    if ($written === false || $written === 0) throw new \RuntimeException('安装锁写入失败');
                    $offset += $written;
                }
                if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
                    throw new \RuntimeException('安装锁无法同步到磁盘');
                }
            } finally {
                fclose($handle);
            }
            @chmod($temporary, $mode);
            if (!link($temporary, $path)) throw new \RuntimeException('安装锁无法原子创建，或已由其他请求创建');
        } finally {
            @unlink($temporary);
        }
        return $path;
    }

    private static function ensureDirectory(string $directory): void
    {
        if ($directory === '' || str_contains($directory, "\0") || is_link($directory)) {
            throw new \RuntimeException('持久配置目录不安全');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('无法创建持久配置目录');
        }
        if (is_link($directory) || !is_dir($directory)) throw new \RuntimeException('持久配置目录不安全');
    }
}
