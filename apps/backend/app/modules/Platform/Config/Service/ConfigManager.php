<?php

namespace app\modules\Platform\Config\Service;

use app\api\ApiException;
use app\modules\Platform\Config\Public\Config as ConfigService;
use app\modules\Platform\Captcha\Public\Captcha;
use app\shared\Authorization\GrantSet;

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

    public function get(array $groups, GrantSet $grants): array
    {
        ConfigAccessPolicy::assertOperation($grants, 'config.read');
        $allowed = $this->allowedGroups();
        if (empty($groups)) {
            $groups = array_values(array_filter(
                $allowed,
                static fn(string $group): bool => ConfigAccessPolicy::canAccessGroup($grants, $group)
            ));
        }

        $result = [];
        foreach ($groups as $group) {
            $group = trim((string) $group);
            if ($group !== '' && in_array($group, $allowed, true)) {
                ConfigAccessPolicy::assertGroup($grants, $group, '读取');
                $result[$group] = $this->redactGroup(
                    $group,
                    ConfigService::getGroup($group),
                    ConfigService::getSchema($group)
                );
            }
        }
        return $result;
    }

    public function schemaGroups(GrantSet $grants): array
    {
        ConfigAccessPolicy::assertOperation($grants, 'config.read');
        return array_values(array_filter(
            ConfigService::getSchemaGroups(),
            static fn(string $group): bool => ConfigAccessPolicy::canAccessGroup($grants, $group)
        ));
    }

    public function update(array $params, GrantSet $grants): void
    {
        ConfigAccessPolicy::assertOperation($grants, 'config.update');
        $allowed = $this->allowedGroups();
        $pending = [];
        foreach ($params as $group => $config) {
            if (!in_array($group, $allowed, true) || !is_array($config)) {
                continue;
            }

            ConfigAccessPolicy::assertGroup($grants, $group, '修改');
            $schema = ConfigService::getSchema($group);
            $validated = [];
            foreach ($config as $key => $value) {
                if (isset($schema[$key])) {
                    if (ConfigAccessPolicy::isSensitive($group, (string) $key, (array) $schema[$key])) {
                        $normalized = $this->normalizeSecretUpdate($group, (string) $key, $value);
                        if ($normalized['apply']) {
                            $validated[$key] = $normalized['value'];
                        }
                        continue;
                    }
                    $validated[$key] = $value;
                }
            }
            if (!empty($validated)) {
                $pending[$group] = $validated;
            }
        }

        if (($pending['captcha']['captcha_enabled'] ?? false) && !Captcha::isReady('captcha')) {
            throw ApiException::validation(['captcha.captcha_enabled' => '人机验证驱动未就绪，请先完成系统验证服务配置']);
        }

        foreach ($pending as $group => $validated) {
            ConfigService::setGroup($group, $validated);
        }
    }

    public function init(GrantSet $grants)
    {
        ConfigAccessPolicy::assertOperation($grants, 'config.init');
        return ConfigService::init();
    }

    public function register(string $group, array $schema, GrantSet $grants)
    {
        ConfigAccessPolicy::assertOperation($grants, 'config.register');
        ConfigAccessPolicy::assertGroup($grants, $group, '注册');
        return ConfigService::register($group, $schema);
    }

    public function reload(?string $group, GrantSet $grants): void
    {
        ConfigAccessPolicy::assertOperation($grants, 'config.reload');
        if ($group !== null && $group !== '') {
            ConfigAccessPolicy::assertGroup($grants, $group, '重载');
        }
        ConfigService::reload($group ?: null);
    }

    public function deleteGroup(string $group, GrantSet $grants): void
    {
        ConfigAccessPolicy::assertOperation($grants, 'config.deleteKey');
        ConfigAccessPolicy::assertGroup($grants, $group, '删除');
        ConfigService::deleteGroup($group);
    }

    public function deleteKey(string $group, string $key, GrantSet $grants): void
    {
        ConfigAccessPolicy::assertOperation($grants, 'config.deleteKey');
        ConfigAccessPolicy::assertGroup($grants, $group, '删除');
        ConfigService::deleteKey($group, $key);
    }

    private function redactGroup(string $group, array $values, array $schema): array
    {
        foreach ($values as $key => $value) {
            if (ConfigAccessPolicy::isSensitive($group, (string) $key, (array) ($schema[$key] ?? []))) {
                $values[$key] = ConfigAccessPolicy::secretMarker($value);
            }
        }
        return $values;
    }

    /** @return array{apply: bool, value: mixed} */
    private function normalizeSecretUpdate(string $group, string $key, $value): array
    {
        // 现有动态表单会把脱敏对象归一为空值；空值只能表示 keep，绝不能隐式清空。
        if ($value === '' || $value === null) {
            return ['apply' => false, 'value' => null];
        }
        if (!is_array($value)) {
            throw ApiException::badRequest("敏感配置 {$group}.{$key} 必须使用 keep/replace/clear 操作");
        }

        $operation = strtolower((string) ($value['$secret'] ?? ''));
        if ($operation === 'keep') {
            return ['apply' => false, 'value' => null];
        }
        if (!in_array($operation, ['replace', 'clear'], true)) {
            throw ApiException::badRequest("敏感配置 {$group}.{$key} 操作不合法");
        }
        if (ConfigService::isEnvironmentManaged($group, $key)) {
            throw ApiException::badRequest("敏感配置 {$group}.{$key} 由环境变量托管，不能在管理端修改");
        }
        if ($operation === 'clear') {
            return ['apply' => true, 'value' => ''];
        }

        $replacement = $value['value'] ?? null;
        if (!is_string($replacement) || $replacement === '') {
            throw ApiException::badRequest("敏感配置 {$group}.{$key} 的替换值不能为空；如需清空请使用 clear");
        }
        return ['apply' => true, 'value' => $replacement];
    }
}
