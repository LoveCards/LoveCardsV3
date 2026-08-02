<?php

namespace app\modules\Platform\Config\Public;

use app\modules\Platform\Config\Infrastructure\ConfigService;

/** Stable read/write facade for platform configuration consumers. */
final class Config
{
    public static function get(string $key, mixed $default = null): mixed { return ConfigService::get($key, $default); }
    public static function getGroup(string $group): array { return ConfigService::getGroup($group); }
    public static function getSchema(string $group): array { return ConfigService::getSchema($group); }
    public static function set(string $key, mixed $value): bool { return ConfigService::set($key, $value); }
    public static function setGroup(string $group, array $config): bool { return ConfigService::setGroup($group, $config); }
    public static function getSchemaGroups(): array { return ConfigService::getSchemaGroups(); }
    public static function register(string $group, array $schema, bool $clearCache = true): array { return ConfigService::register($group, $schema, $clearCache); }
    public static function reload(?string $group = null): void { ConfigService::reload($group); }
    public static function deleteGroup(string $group): bool { return ConfigService::deleteGroup($group); }
    public static function deleteKey(string $group, string $key): bool { return ConfigService::deleteKey($group, $key); }
    public static function init(): array { return ConfigService::init(); }
}
