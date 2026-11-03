<?php

namespace app\modules\Theme\Application;

use app\modules\Theme\Service\ThemeManager;

final class UpdateThemeConfig
{
    public function execute(string $id, string $release, array $values): bool { ThemeManager::updateConfig($id, $release, $values); return true; }
}
