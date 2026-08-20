<?php
declare(strict_types=1);

namespace app\shared\SystemPaths;

/**
 * Resolves the one physical root used by the local storage driver and lifecycle backups.
 */
final class LocalStoragePath
{
    /** @param array<string, mixed> $channelConfig */
    public static function root(?SystemPaths $paths, string $applicationRoot, array $channelConfig = []): string
    {
        $instanceRoot = self::configuredInstanceRoot();
        if ($instanceRoot !== null) {
            $paths ??= new SystemPaths($instanceRoot, rtrim($applicationRoot, '/\\'));
            return $paths->storageRoot();
        }

        $configured = trim((string) ($channelConfig['root'] ?? 'public/storage'));
        if ($configured === '') $configured = 'public/storage';
        $configured = str_replace('\\', '/', $configured);
        if (
            strpos($configured, "\0") !== false
            || preg_match('#^(?:[A-Za-z]:/|/|//)#', $configured) === 1
        ) {
            throw new \RuntimeException('本地存储根目录必须是应用内相对路径');
        }

        $segments = [];
        foreach (explode('/', $configured) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') throw new \RuntimeException('本地存储根目录不能包含上级路径');
            $segments[] = $segment;
        }
        if ($segments === []) throw new \RuntimeException('本地存储根目录不能为空');

        return rtrim($applicationRoot, '/\\') . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
    }

    public static function configuredInstanceRoot(): ?string
    {
        $value = getenv(SystemPathsResolver::ENV_INSTANCE_ROOT);
        if (($value === false || trim((string) $value) === '') && function_exists('env')) {
            $value = env(SystemPathsResolver::ENV_INSTANCE_ROOT, '');
        }
        if (!is_string($value) || trim($value) === '') return null;
        return trim($value);
    }

    public static function safeRelative(string $path): bool
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || strpos($path, "\0") !== false || $path[0] === '/') return false;
        if (preg_match('#^[A-Za-z]:/#', $path) === 1) return false;
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') return false;
        }
        return true;
    }

    public static function ensureRoot(string $root): string
    {
        if (is_link($root)) throw new \RuntimeException('本地存储根目录不能是 symlink');
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new \RuntimeException('无法创建本地存储根目录');
        }
        $real = realpath($root);
        if ($real === false || !is_dir($real) || is_link($root)) {
            throw new \RuntimeException('本地存储根目录不可用');
        }
        return $real;
    }

    public static function prepareDestination(string $root, string $relative): string
    {
        if (!self::safeRelative($relative)) throw new \RuntimeException('本地存储路径不安全');
        $rootReal = self::ensureRoot($root);
        $parts = explode('/', str_replace('\\', '/', $relative));
        $filename = array_pop($parts);
        $directory = $rootReal;
        foreach ($parts as $part) {
            $directory .= DIRECTORY_SEPARATOR . $part;
            if (is_link($directory)) throw new \RuntimeException('本地存储路径不能经过 symlink');
            if (!is_dir($directory) && !mkdir($directory, 0750) && !is_dir($directory)) {
                throw new \RuntimeException('无法创建本地存储目录');
            }
        }
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($destination) || is_link($destination)) {
            throw new \RuntimeException('本地存储目标已存在');
        }
        return $destination;
    }

    public static function resolveExisting(string $root, string $relative): ?string
    {
        if (!self::safeRelative($relative) || !is_dir($root) || is_link($root)) return null;
        $rootReal = realpath($root);
        if ($rootReal === false) return null;
        $candidate = $rootReal;
        foreach (explode('/', str_replace('\\', '/', $relative)) as $part) {
            $candidate .= DIRECTORY_SEPARATOR . $part;
            if (is_link($candidate)) return null;
        }
        $real = realpath($candidate);
        if ($real === false || !is_file($real) || !self::within($real, $rootReal)) return null;
        return $real;
    }

    public static function within(string $candidate, string $root): bool
    {
        $candidate = str_replace('\\', '/', rtrim($candidate, '/\\'));
        $root = str_replace('\\', '/', rtrim($root, '/\\'));
        if (DIRECTORY_SEPARATOR === '\\') {
            $candidate = strtolower($candidate);
            $root = strtolower($root);
        }
        return $candidate === $root || strncmp($candidate, $root . '/', strlen($root) + 1) === 0;
    }
}
