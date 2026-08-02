<?php

namespace app\api\service\System;

use app\common\service\Config as ConfigService;

/**
 * 配置管理用例。
 *
 * Controller 只负责 HTTP 参数，配置分组筛选、更新和生命周期操作集中在这里。
 */
final class ConfigManager
{
    public function allowedGroups(): array
    {
        $groups = ConfigService::getSchemaGroups();
        return empty($groups) ? ['core', 'upload', 'cards', 'comments', 'user', 'version', 'captcha'] : $groups;
    }

    public function get(array $groups = []): array
    {
        $allowed = $this->allowedGroups();
        if (empty($groups)) {
            $groups = $allowed;
        }

        $result = [];
        foreach ($groups as $group) {
            $group = trim((string) $group);
            if ($group !== '' && in_array($group, $allowed, true)) {
                $result[$group] = ConfigService::getGroup($group);
            }
        }
        return $result;
    }

    public function schemaGroups(): array
    {
        return ConfigService::getSchemaGroups();
    }

    public function update(array $params): void
    {
        $allowed = $this->allowedGroups();
        foreach ($params as $group => $config) {
            if (!in_array($group, $allowed, true) || !is_array($config)) {
                continue;
            }

            $schema = ConfigService::getSchema($group);
            $validated = [];
            foreach ($config as $key => $value) {
                if (isset($schema[$key])) {
                    $validated[$key] = $value;
                }
            }
            if (!empty($validated)) {
                ConfigService::setGroup($group, $validated);
            }
        }
    }

    public function init()
    {
        return ConfigService::init();
    }

    public function register(string $group, array $schema)
    {
        return ConfigService::register($group, $schema);
    }

    public function reload(?string $group): void
    {
        ConfigService::reload($group ?: null);
    }

    public function deleteGroup(string $group): void
    {
        ConfigService::deleteGroup($group);
    }

    public function deleteKey(string $group, string $key): void
    {
        ConfigService::deleteKey($group, $key);
    }
}
