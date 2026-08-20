<?php

namespace app\modules\Theme\Application;

use app\modules\Theme\Service\ThemeManager;

final class InstallTheme
{
    public function execute(string $zipPath, ?string $themeRoot = null): array
    {
        return ThemeManager::install($zipPath);
    }
}
