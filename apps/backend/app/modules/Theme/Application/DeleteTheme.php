<?php

namespace app\modules\Theme\Application;

use app\modules\Theme\Service\ThemeManager;

final class DeleteTheme
{
    public function execute(string $id, string $release): bool { ThemeManager::delete($id, $release); return true; }
}
