<?php

namespace app\modules\Theme\Public;

use think\Response;
use app\modules\Theme\Runtime\ThemeGateway;
use app\modules\Theme\Service\ThemeManager;
use app\modules\Theme\Application\ManageTheme;

final class Theme
{
    public static function boot(string $uri): Response { return ThemeGateway::page(request()); }
    public static function asset(): Response { return ThemeGateway::asset(request()); }
    public static function context(): array { return ThemeGateway::activeContext(request()); }
    public static function active(): array { return ThemeGateway::activeContext(request()); }
    public static function config(string $name = ''): array { return ThemeGateway::activeContext(request())['config_values'] ?? []; }
    public static function list(): array { return ThemeManager::list(); }
    public static function maxZipBytes(): int { return ThemeManager::maxZipBytes(); }
    public static function install(string $zipPath, int $actorId): array { return app(ManageTheme::class)->install($zipPath, $actorId); }
    public static function activate(string $id, string $release, int $actorId, string $hostname): void { app(ManageTheme::class)->activate($id, $release, $actorId, $hostname); }
    public static function updateConfig(string $id, string $release, array $values, int $actorId, string $hostname): void { app(ManageTheme::class)->updateConfig($id, $release, $values, $actorId, $hostname); }
    public static function delete(string $id, string $release, int $actorId): void { app(ManageTheme::class)->delete($id, $release, $actorId); }
}
