<?php

namespace app\modules\Theme\Application;

use app\modules\Theme\Runtime\ThemeEngine;

final class DeleteTheme
{
    public function execute(string $name): bool { return ThemeEngine::deleteTheme($name); }
}
