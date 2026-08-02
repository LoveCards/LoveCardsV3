<?php

namespace app\modules\Theme\Service;

use app\modules\Theme\Runtime\ThemeEngine;
use app\modules\Theme\Application\InstallTheme;
use app\modules\Theme\Application\ActivateTheme;
use app\modules\Theme\Application\UpdateThemeConfig;
use app\modules\Theme\Application\DeleteTheme;

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
        return (new InstallTheme())->execute($zipPath);
    }

    public static function activate(string $name): void
    {
        (new ActivateTheme())->execute($name);
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
        (new UpdateThemeConfig())->execute($params);
    }

    public static function freezeConfig(): void
    {
        ThemeEngine::freezeConfig();
    }

    public static function delete(string $name): void
    {
        (new DeleteTheme())->execute($name);
    }

    public static function maxZipBytes(): int
    {
        return ThemeEngine::MAX_THEME_ZIP_BYTES;
    }
}
