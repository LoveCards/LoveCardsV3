<?php
declare(strict_types=1);

namespace app\shared\SystemPaths;

/**
 * LoveCards 实例目录的不可变视图。
 *
 * 本对象只解析和校验路径，不创建目录，也不切换 active Release。
 */
final class SystemPaths
{
    private string $instanceRoot;
    private string $activeReleaseRoot;
    private string $bootstrapRoot;
    private string $recoveryRoot;
    private string $releasesRoot;
    private string $sharedRoot;
    private string $activeReleasePointer;

    public function __construct(string $instanceRoot, string $activeReleaseRoot)
    {
        $this->instanceRoot = self::normalizeAbsolute($instanceRoot, 'instance_root');
        $this->activeReleaseRoot = self::normalizeAbsolute($activeReleaseRoot, 'active_release_root');
        $this->bootstrapRoot = self::join($this->instanceRoot, 'bootstrap');
        $this->recoveryRoot = self::join($this->instanceRoot, 'recovery');
        $this->releasesRoot = self::join($this->instanceRoot, 'releases');
        $this->sharedRoot = self::join($this->instanceRoot, 'shared');
        $this->activeReleasePointer = self::join($this->instanceRoot, 'current');

        foreach ([$this->bootstrapRoot, $this->recoveryRoot, $this->sharedRoot, $this->activeReleasePointer] as $persistentPath) {
            if (self::isWithin($persistentPath, $this->activeReleaseRoot)) {
                throw new \InvalidArgumentException('实例持久路径不能位于 active Release 内');
            }
        }
    }

    public function instanceRoot(): string { return $this->instanceRoot; }
    public function activeReleaseRoot(): string { return $this->activeReleaseRoot; }
    public function bootstrapRoot(): string { return $this->bootstrapRoot; }
    public function recoveryRoot(): string { return $this->recoveryRoot; }
    public function releasesRoot(): string { return $this->releasesRoot; }
    public function sharedRoot(): string { return $this->sharedRoot; }
    public function activeReleasePointer(): string { return $this->activeReleasePointer; }

    public function configRoot(): string { return self::join($this->sharedRoot, 'config'); }
    public function keysRoot(): string { return self::join($this->sharedRoot, 'keys'); }
    public function stateRoot(): string { return self::join($this->sharedRoot, 'state'); }
    public function storageRoot(): string { return self::join($this->sharedRoot, 'storage'); }
    public function themesRoot(): string { return self::join($this->sharedRoot, 'themes'); }
    public function runtimeRoot(): string { return self::join($this->sharedRoot, 'runtime'); }
    public function logsRoot(): string { return self::join($this->sharedRoot, 'logs'); }
    public function backupsRoot(): string { return self::join($this->sharedRoot, 'backups'); }
    public function upgradeRoot(): string { return self::join($this->sharedRoot, 'upgrade'); }

    public function releaseRoot(string $releaseId): string
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $releaseId)) {
            throw new \InvalidArgumentException('Release ID 不合法');
        }
        return self::join($this->releasesRoot, $releaseId);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'instance_root' => $this->instanceRoot,
            'active_release_root' => $this->activeReleaseRoot,
            'bootstrap_root' => $this->bootstrapRoot,
            'recovery_root' => $this->recoveryRoot,
            'releases_root' => $this->releasesRoot,
            'active_release_pointer' => $this->activeReleasePointer,
            'shared_root' => $this->sharedRoot,
            'config_root' => $this->configRoot(),
            'keys_root' => $this->keysRoot(),
            'state_root' => $this->stateRoot(),
            'storage_root' => $this->storageRoot(),
            'themes_root' => $this->themesRoot(),
            'runtime_root' => $this->runtimeRoot(),
            'logs_root' => $this->logsRoot(),
            'backups_root' => $this->backupsRoot(),
            'upgrade_root' => $this->upgradeRoot(),
        ];
    }

    private static function join(string $root, string $child): string
    {
        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $child;
    }

    private static function isWithin(string $candidate, string $parent): bool
    {
        $candidate = str_replace('\\', '/', rtrim($candidate, '/\\'));
        $parent = str_replace('\\', '/', rtrim($parent, '/\\'));
        if (preg_match('/^(?:[A-Za-z]:|\/\/)/', $candidate) || preg_match('/^(?:[A-Za-z]:|\/\/)/', $parent)) {
            $candidate = strtolower($candidate);
            $parent = strtolower($parent);
        }
        return $candidate === $parent || strncmp($candidate, $parent . '/', strlen($parent) + 1) === 0;
    }

    private static function normalizeAbsolute(string $path, string $field): string
    {
        $path = trim($path);
        if ($path === '' || strpos($path, "\0") !== false) {
            throw new \InvalidArgumentException($field . ' 必须是绝对路径');
        }

        $path = str_replace('\\', '/', $path);
        $root = '';
        $rest = '';
        if (preg_match('#^([A-Za-z]:)/(.*)$#', $path, $matches)) {
            $root = strtoupper($matches[1]) . '/';
            $rest = $matches[2];
        } elseif (preg_match('#^//([^/]+)/([^/]+)(?:/(.*))?$#', $path, $matches)) {
            $root = '//' . $matches[1] . '/' . $matches[2] . '/';
            $rest = $matches[3] ?? '';
        } elseif ($path[0] === '/') {
            $root = '/';
            $rest = substr($path, 1);
        } else {
            throw new \InvalidArgumentException($field . ' 必须是绝对路径');
        }

        $segments = [];
        foreach (explode('/', $rest) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') {
                if ($segments === []) throw new \InvalidArgumentException($field . ' 不能越过文件系统根目录');
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        $normalized = $root . implode('/', $segments);
        if ($segments === []) $normalized = rtrim($root, '/') . '/';
        return DIRECTORY_SEPARATOR === '\\' ? str_replace('/', '\\', $normalized) : $normalized;
    }
}
