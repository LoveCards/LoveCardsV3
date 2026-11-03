<?php

namespace app\modules\Theme\Service;

use app\modules\Theme\Infrastructure\FilesystemThemeCatalog;
use app\modules\Theme\Infrastructure\SecureThemeInstaller;
use app\modules\Theme\Infrastructure\ThemeBindingRepository;
use app\modules\Theme\Runtime\ThemeGateway;

/** Theme management service for immutable SPA releases and host bindings. */
final class ThemeManager
{
    public static function list(): array
    {
        $active = self::binding();
        return array_map(static function (array $item) use ($active): array {
            $item['active'] = $active !== null
                && $active['theme_id'] === $item['id']
                && $active['release_key'] === $item['release'];
            return $item;
        }, FilesystemThemeCatalog::list());
    }

    public static function install(string $zipPath): array
    {
        return (new SecureThemeInstaller())->install($zipPath);
    }

    public static function activate(string $id, string $release): void
    {
        $theme = FilesystemThemeCatalog::inspect($id, $release);
        if ($theme === null) throw new \RuntimeException('主题 Release 不存在或不兼容');
        if ((string)$theme['id'] !== $id) throw new \RuntimeException('主题 id 与 Release 不匹配');

        $repository = new ThemeBindingRepository();
        $binding = self::binding();
        $sameTheme = $binding !== null && $binding['theme_id'] === $id;
        $overrides = $sameTheme ? self::filterOverrides($theme, $binding['config_overrides'] ?? []) : [];
        $repository->save([
            'id' => $binding['id'] ?? null,
            'hostname' => self::host(),
            'theme_id' => $id,
            'release_key' => $release,
            'config_overrides' => $overrides,
            'enabled' => 1,
        ]);
    }

    public static function active(): array
    {
        return ThemeGateway::activeContext(request());
    }

    public static function updateConfig(string $id, string $release, array $values): void
    {
        $binding = self::binding();
        if ($binding === null || $binding['theme_id'] !== $id || $binding['release_key'] !== $release) throw new \RuntimeException('主题绑定已变化，请刷新后重试');
        $theme = FilesystemThemeCatalog::inspect($id, $release);
        if ($theme === null) throw new \RuntimeException('主题 Release 不存在或不兼容');
        self::assertConfigValues($theme, $values);
        $binding['config_overrides'] = array_merge($binding['config_overrides'] ?? [], $values);
        (new ThemeBindingRepository())->save($binding);
    }

    public static function delete(string $id, string $release): void
    {
        $path = FilesystemThemeCatalog::resolve($id, $release);
        if ($path === null) throw new \RuntimeException('主题 Release 不存在');
        if ((new ThemeBindingRepository())->isBound($id, $release)) throw new \RuntimeException('不能删除正在使用的主题 Release');
        self::removeDirectory($path);
    }

    public static function maxZipBytes(): int { return SecureThemeInstaller::MAX_ZIP_BYTES; }

    private static function host(): string { return (string)request()->host(); }

    private static function binding(): ?array
    {
        return (new ThemeBindingRepository())->find(self::host());
    }

    private static function filterOverrides(array $theme, array $overrides): array
    {
        $allowed = array_keys($theme['config'] ?? []);
        $filtered = [];
        foreach ($overrides as $key => $value) if (in_array($key, $allowed, true)) $filtered[$key] = $value;
        self::assertConfigValues($theme, $filtered);
        return $filtered;
    }

    private static function assertConfigValues(array $theme, array $values): void
    {
        $schema = $theme['config'] ?? [];
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, $schema)) throw new \RuntimeException('不允许的主题配置项: ' . $key);
            $definition = $schema[$key];
            $type = $definition['type'] ?? 'string';
            $valid = match ($type) {
                'string' => is_string($value),
                'boolean' => is_bool($value),
                'integer' => is_int($value) && !is_bool($value),
                'number' => (is_int($value) || is_float($value)) && !is_bool($value),
                default => false,
            };
            if (!$valid) throw new \RuntimeException('主题配置项类型错误: ' . $key);
            if (($definition['format'] ?? null) === 'color' && !preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string)$value)) throw new \RuntimeException('主题颜色配置错误: ' . $key);
            if (($definition['format'] ?? null) === 'uri' && filter_var($value, FILTER_VALIDATE_URL) === false) throw new \RuntimeException('主题 URI 配置错误: ' . $key);
            if (($definition['format'] ?? null) === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) throw new \RuntimeException('主题邮箱配置错误: ' . $key);
            if (isset($definition['maxLength']) && is_string($value) && mb_strlen($value) > (int)$definition['maxLength']) throw new \RuntimeException('主题配置项过长: ' . $key);
        }
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            if ($file->isDir() && !is_link($file->getPathname())) @rmdir($file->getPathname());
            else @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
