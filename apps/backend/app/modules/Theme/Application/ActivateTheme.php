<?php

namespace app\modules\Theme\Application;

use app\modules\Theme\Service\ThemeManager;

final class ActivateTheme
{
    public function execute(string $id, string $release): bool { ThemeManager::activate($id, $release); return true; }
}
