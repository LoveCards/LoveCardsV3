<?php

namespace app\modules\Theme\Infrastructure;

use app\modules\Theme\Domain\ThemeManifest;
use app\shared\SystemPaths\LocalStoragePath;
use app\shared\SystemPaths\SystemPaths;

final class FilesystemThemeCatalog
{
    private const RELEASE_PATTERN = '/^[0-9]+\\.[0-9]+\\.[0-9]+(?:-[0-9A-Za-z.-]+)?(?:\\+[0-9A-Za-z.-]+)?-[a-f0-9]{16}$/';

    public static function root(): string
    {
        $legacyRoot = rtrim(root_path(), '/\\');
        $instanceRoot = LocalStoragePath::configuredInstanceRoot();
        if ($instanceRoot === null) return $legacyRoot . DIRECTORY_SEPARATOR . 'themes';
        return (new SystemPaths($instanceRoot, $legacyRoot))->themesRoot();
    }

    public static function resolve(string $themeId, string $releaseKey): ?string
    {
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/', $themeId) || !preg_match(self::RELEASE_PATTERN, $releaseKey)) return null;
        $root = realpath(self::root());
        if ($root === false) return null;
        $releaseRoot = realpath($root . DIRECTORY_SEPARATOR . '.releases' . DIRECTORY_SEPARATOR . $themeId);
        if ($releaseRoot === false || strpos($releaseRoot, $root . DIRECTORY_SEPARATOR) !== 0) return null;
        $path = realpath($releaseRoot . DIRECTORY_SEPARATOR . $releaseKey);
        if ($path === false || strpos($path, $releaseRoot . DIRECTORY_SEPARATOR) !== 0 || !is_dir($path)) return null;
        return $path;
    }

    public static function inspect(string $themeId, string $releaseKey): ?array
    {
        $path = self::resolve($themeId, $releaseKey);
        if ($path === null) return null;
        try { return ThemeManifest::load($path); }
        catch (\Throwable $e) { return null; }
    }

    public static function list(): array
    {
        $releasesRoot = self::root() . DIRECTORY_SEPARATOR . '.releases';
        if (!is_dir($releasesRoot)) return [];
        $result = [];
        foreach (scandir($releasesRoot) ?: [] as $id) {
            if ($id === '.' || $id === '..' || !is_dir($releasesRoot . DIRECTORY_SEPARATOR . $id)) continue;
            $themeRoot = $releasesRoot . DIRECTORY_SEPARATOR . $id;
            foreach (scandir($themeRoot) ?: [] as $release) {
                if ($release === '.' || $release === '..') continue;
                $manifest = self::inspect($id, $release);
                if ($manifest) $result[] = self::summary($manifest, $id, $release);
            }
        }
        usort($result, static fn(array $a, array $b): int => strcmp($a['id'] . '/' . $a['release'], $b['id'] . '/' . $b['release']));
        return $result;
    }

    private static function summary(array $manifest, string $id, string $release): array
    {
        return [
            'id' => $id,
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'mode' => $manifest['mode'],
            'release' => $release,
            'author' => $manifest['author'] ?? '',
            'description' => $manifest['description'] ?? '',
        ];
    }
}
