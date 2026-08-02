<?php

namespace app\api\service\Theme;

use app\frontend\service\ThemeEngine;

/**
 * 主题管理用例；主题文件和渲染实现由前端主题引擎负责。
 */
final class ThemeManager
{
    public static function list(): array
    {
        return ThemeEngine::listThemes();
    }

    public static function install(string $zipPath): array
    {
        return ThemeEngine::installTheme($zipPath);
    }

    public static function activate(string $name): void
    {
        ThemeEngine::activateTheme($name);
    }

    public static function active(): array
    {
        return ThemeEngine::getActive();
    }

    public static function config(string $name): array
    {
        return ThemeEngine::getThemeConfig($name);
    }

    public static function updateConfig(array $params): void
    {
        ThemeEngine::updateThemeConfig($params);
    }

    public static function freezeConfig(): void
    {
        ThemeEngine::freezeConfig();
    }

    public static function delete(string $name): void
    {
        ThemeEngine::deleteTheme($name);
    }

    public static function maxZipBytes(): int
    {
        return ThemeEngine::MAX_THEME_ZIP_BYTES;
    }
}
