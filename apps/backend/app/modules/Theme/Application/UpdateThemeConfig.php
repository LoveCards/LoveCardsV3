<?php

namespace app\modules\Theme\Application;

use app\modules\Theme\Runtime\ThemeEngine;

final class UpdateThemeConfig
{
    public function execute(array $values): bool { return ThemeEngine::updateThemeConfig($values); }
}
