<?php

namespace app\modules\Theme\Public;

use think\Response;
use app\modules\Theme\Runtime\ThemeGateway;

final class Theme
{
    public static function boot(string $uri): Response { return ThemeGateway::page(request()); }
    public static function asset(): Response { return ThemeGateway::asset(request()); }
    public static function context(): array { return ThemeGateway::activeContext(request()); }
    public static function active(): array { return ThemeGateway::activeContext(request()); }
    public static function config(string $name = ''): array { return ThemeGateway::activeContext(request())['config_values'] ?? []; }
}
