<?php
declare(strict_types=1);

namespace LoveCards\Bootstrap;

/**
 * Dependency-free bootstrap used before Composer and ThinkPHP are loaded.
 *
 * A missing pointer keeps legacy/development installs on their current code.
 * Once a pointer exists it is fail-closed: only a real, direct child of the
 * configured releases directory with a matching manifest can be dispatched.
 */
final class ReleaseBootstrap
{
    public const HEALTH_PATH = '/_lc/release-health';
    public const RELEASE_HEADER = 'X-LoveCards-Release-Id';
    private const DISPATCH_ROOT = 'LOVECARDS_RELEASE_DISPATCH_ROOT';

    public static function webEntry(string $sourceRoot): ?string
    {
        return self::entry($sourceRoot, 'public/index.php');
    }

    public static function cliEntry(string $sourceRoot): ?string
    {
        return self::entry($sourceRoot, 'think');
    }

    public static function resolve(string $sourceRoot): string
    {
        $sourceRoot = self::realDirectory($sourceRoot, '当前代码目录');

        if (defined(self::DISPATCH_ROOT)) {
            $dispatchedRoot = (string) constant(self::DISPATCH_ROOT);
            if (!self::samePath($sourceRoot, $dispatchedRoot)) {
                throw new \RuntimeException('Release 启动入口发生递归跳转');
            }
            return $sourceRoot;
        }

        [$instanceRoot, $explicit] = self::instanceRoot($sourceRoot);
        $pointer = self::join($instanceRoot, 'current');
        $installLock = self::join($instanceRoot, 'shared/state/install.lock');
        $managed = $explicit || file_exists($pointer) || is_link($pointer) || is_file($installLock);

        if (!$managed) {
            self::unsetEnvironment('LC_ACTIVE_RELEASE_ID');
            return $sourceRoot;
        }

        self::prepareSharedRuntime($instanceRoot);
        if (!file_exists($pointer) && !is_link($pointer)) {
            self::unsetEnvironment('LC_ACTIVE_RELEASE_ID');
            return $sourceRoot;
        }
        if (is_link($pointer) || !is_file($pointer)) {
            throw new \RuntimeException('active Release 指针不是安全的普通文件');
        }

        $size = filesize($pointer);
        if ($size === false || $size < 1 || $size > 256) {
            throw new \RuntimeException('active Release 指针大小不合法');
        }
        $releaseId = trim((string) file_get_contents($pointer));
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $releaseId) !== 1) {
            throw new \RuntimeException('active Release 指针内容不合法');
        }

        $releasesRoot = self::join($instanceRoot, 'releases');
        if (is_link($releasesRoot)) {
            throw new \RuntimeException('Release 根目录不能是 symlink');
        }
        $releasesRoot = self::realDirectory($releasesRoot, 'Release 根目录');
        $candidate = self::join($releasesRoot, $releaseId);
        if (is_link($candidate)) {
            throw new \RuntimeException('active Release 目录不能是 symlink');
        }
        $releaseRoot = self::realDirectory($candidate, 'active Release 目录');
        if (!self::isWithin($releaseRoot, $releasesRoot)) {
            throw new \RuntimeException('active Release 路径越界');
        }

        self::assertManifest($releaseRoot, $releaseId);
        self::setEnvironment('LC_ACTIVE_RELEASE_ID', $releaseId);
        return $releaseRoot;
    }

    public static function activeReleaseId(): ?string
    {
        $value = getenv('LC_ACTIVE_RELEASE_ID');
        return is_string($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $value) === 1
            ? $value
            : null;
    }

    public static function sharedRuntimeRoot(): ?string
    {
        $value = getenv('LC_RUNTIME_ROOT');
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    public static function isHealthRequest(array $server): bool
    {
        if (strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET')) !== 'GET') return false;
        $path = parse_url((string) ($server['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        return is_string($path) && $path === self::HEALTH_PATH;
    }

    private static function entry(string $sourceRoot, string $relativeEntry): ?string
    {
        $sourceRoot = self::realDirectory($sourceRoot, '当前代码目录');
        $targetRoot = self::resolve($sourceRoot);
        if (self::samePath($sourceRoot, $targetRoot)) return null;

        $entry = self::join($targetRoot, $relativeEntry);
        if (is_link($entry) || !is_file($entry)) {
            throw new \RuntimeException('目标 Release 缺少安全的启动入口');
        }
        $entryReal = realpath($entry);
        if ($entryReal === false || !self::isWithin($entryReal, $targetRoot)) {
            throw new \RuntimeException('目标 Release 启动入口越界');
        }
        if (defined(self::DISPATCH_ROOT)) {
            throw new \RuntimeException('Release 启动入口发生重复跳转');
        }
        define(self::DISPATCH_ROOT, $targetRoot);
        return $entryReal;
    }

    /** @return array{0:string,1:bool} */
    private static function instanceRoot(string $sourceRoot): array
    {
        $configured = getenv('LC_INSTANCE_ROOT');
        $explicit = is_string($configured) && trim($configured) !== '';
        $raw = $explicit
            ? trim((string) $configured)
            : dirname($sourceRoot) . DIRECTORY_SEPARATOR . '.lovecards-instance';
        return [self::normalizeAbsolute($raw, 'LC_INSTANCE_ROOT'), $explicit];
    }

    private static function prepareSharedRuntime(string $instanceRoot): void
    {
        $shared = self::join($instanceRoot, 'shared');
        $runtime = self::join($shared, 'runtime');
        $logs = self::join($shared, 'logs');
        foreach ([$instanceRoot, $shared, $runtime, $logs] as $directory) {
            if (is_link($directory)) throw new \RuntimeException('实例持久目录不能是 symlink');
            if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new \RuntimeException('无法创建实例持久运行目录');
            }
            if (is_link($directory) || !is_dir($directory)) {
                throw new \RuntimeException('实例持久运行目录不安全');
            }
        }
        self::setEnvironment('LC_INSTANCE_ROOT_RESOLVED', $instanceRoot);
        self::setEnvironment('LC_RUNTIME_ROOT', $runtime);
        self::setEnvironment('LC_LOG_ROOT', $logs);
    }

    private static function assertManifest(string $releaseRoot, string $releaseId): void
    {
        $path = self::join($releaseRoot, 'release.json');
        if (is_link($path) || !is_file($path)) throw new \RuntimeException('active Release 缺少 Manifest');
        $size = filesize($path);
        if ($size === false || $size < 2 || $size > 1048576) {
            throw new \RuntimeException('active Release Manifest 大小不合法');
        }
        try {
            $manifest = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('active Release Manifest 不是合法 JSON', 0, $exception);
        }
        if (!is_array($manifest) || !hash_equals($releaseId, (string) ($manifest['release_id'] ?? ''))) {
            throw new \RuntimeException('active Release Manifest 与指针不一致');
        }
    }

    private static function realDirectory(string $path, string $label): string
    {
        if (is_link($path) || !is_dir($path)) throw new \RuntimeException($label . '不存在或不安全');
        $real = realpath($path);
        if ($real === false) throw new \RuntimeException($label . '无法解析');
        return rtrim($real, '/\\');
    }

    private static function normalizeAbsolute(string $path, string $label): string
    {
        if ($path === '' || strpos($path, "\0") !== false) {
            throw new \RuntimeException($label . '必须是绝对路径');
        }
        $path = str_replace('\\', '/', $path);
        if (preg_match('#^([A-Za-z]:)/(.*)$#', $path, $match)) {
            $prefix = strtoupper($match[1]) . '/';
            $tail = $match[2];
        } elseif (preg_match('#^//([^/]+)/([^/]+)(?:/(.*))?$#', $path, $match)) {
            $prefix = '//' . $match[1] . '/' . $match[2] . '/';
            $tail = $match[3] ?? '';
        } elseif ($path[0] === '/') {
            $prefix = '/';
            $tail = substr($path, 1);
        } else {
            throw new \RuntimeException($label . '必须是绝对路径');
        }
        $segments = [];
        foreach (explode('/', $tail) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') {
                if ($segments === []) throw new \RuntimeException($label . '越过文件系统根目录');
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        $normalized = $prefix . implode('/', $segments);
        if ($segments === []) $normalized = rtrim($prefix, '/') . '/';
        return DIRECTORY_SEPARATOR === '\\' ? str_replace('/', '\\', $normalized) : $normalized;
    }

    private static function join(string $root, string $child): string
    {
        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($child, '/\\'));
    }

    private static function samePath(string $left, string $right): bool
    {
        return self::normalizedForComparison($left) === self::normalizedForComparison($right);
    }

    private static function isWithin(string $candidate, string $root): bool
    {
        $candidate = self::normalizedForComparison($candidate);
        $root = self::normalizedForComparison($root);
        return $candidate === $root || str_starts_with($candidate, $root . '/');
    }

    private static function normalizedForComparison(string $path): string
    {
        $path = str_replace('\\', '/', rtrim($path, '/\\'));
        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }

    private static function setEnvironment(string $name, string $value): void
    {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    private static function unsetEnvironment(string $name): void
    {
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }
}
