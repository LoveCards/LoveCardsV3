<?php

namespace app\modules\Theme\Application;

use app\modules\Theme\Runtime\ThemeEngine;

final class InstallTheme
{
    public function execute(string $zipPath, ?string $themeRoot = null): array
    {
        return ThemeEngine::installTheme($zipPath, $themeRoot);
    }
}
