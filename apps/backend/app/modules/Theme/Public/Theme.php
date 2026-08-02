<?php

namespace app\modules\Theme\Public;

use think\Response;
use app\modules\Theme\Runtime\ThemeEngine;

final class Theme
{
    public static function boot(string $uri): Response { return ThemeEngine::boot($uri); }
    public static function active(): array { return ThemeEngine::getActive(); }
    public static function config(string $name): array { return ThemeEngine::getThemeConfig($name); }
}
