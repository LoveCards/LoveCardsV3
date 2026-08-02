<?php

namespace app\modules\Theme\Application;

use app\modules\Theme\Runtime\ThemeEngine;

final class ActivateTheme
{
    public function execute(string $name): bool { return ThemeEngine::activateTheme($name); }
}
