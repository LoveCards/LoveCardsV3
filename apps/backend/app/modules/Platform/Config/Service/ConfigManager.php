<?php

namespace app\modules\Platform\Config\Service;

use app\api\ApiException;
use app\modules\Platform\Auth\Public\RegistrationPolicy;
use app\modules\Platform\Config\Public\Config as ConfigService;
use app\modules\Platform\Captcha\Public\Captcha;
use app\modules\Platform\Config\Public\RedisConnectionSettings;
use app\modules\Platform\Config\Public\DeploymentNamespace;
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
                $snapshot = ConfigService::getSnapshot($group);
                $result[$group] = $this->redactGroup(
                    $group,
                    $snapshot['values'],
                    ConfigService::getSchema($group)
                ) + ['__revision' => $snapshot['revision']];
                if ($group === 'captcha') unset($result[$group]['code_channel']);
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

    /** @return array{ready:bool,reason_code:?string,source:string} */
    public function testRedis(GrantSet $grants): array
    {
        ConfigAccessPolicy::assertOperation($grants, 'operations.runtime.configure');
        $result = RedisConnectionSettings::test();
        return ['ready' => $result['ready'], 'reason_code' => $result['reason_code'], 'source' => $result['source']];
    }

    public function update(array $params, GrantSet $grants): array
    {
        ConfigAccessPolicy::assertOperation($grants, 'config.update');
        $allowed = $this->allowedGroups();
        $pending = [];
        $expected = [];
        $schemas = [];
        foreach ($params as $group => $config) {
            if (!in_array($group, $allowed, true) || !is_array($config)) {
                continue;
            }

            ConfigAccessPolicy::assertGroup($grants, $group, '修改');
            $schema = ConfigService::getSchema($group);
            $schemas[$group] = $schema;
            $validated = [];
            foreach ($config as $key => $value) {
                // 已退役的全局渠道选择只保留旧数据兼容，渠道由验证场景决定。
                if ($group === 'captcha' && $key === 'code_channel') continue;
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
                if ($group === 'redis') {
                    $this->validateRedisUpdate($validated, ConfigService::getGroup('redis'));
                }
                $pending[$group] = $validated;
                $revision = $config['__revision'] ?? null;
                if (!is_int($revision) || $revision < 1) {
                    throw ApiException::validation(["{$group}.__revision" => ['请先加载配置并提交读取时的版本']], '配置版本缺失或无效', 'config-revision-required');
                }
                $expected[$group] = $revision;
            }
        }

        $captchaUpdate = $pending['captcha'] ?? [];
        if (array_key_exists('captcha_enabled', $captchaUpdate) || array_key_exists('default_captcha_driver', $captchaUpdate)) {
            $current = ConfigService::getGroup('captcha');
            $enabled = (bool) ($captchaUpdate['captcha_enabled'] ?? $current['captcha_enabled'] ?? false);
            $driver = (string) ($captchaUpdate['default_captcha_driver'] ?? $current['default_captcha_driver'] ?? 'geetest_v4');
            if ($enabled && !Captcha::isReady('captcha', $driver)) {
                throw ApiException::validation(['captcha.default_captcha_driver' => '人机验证驱动未就绪，请先完成系统验证服务配置']);
            }
        }

        $registrationKeys = ['registration_mode', 'registration_sms_verification', 'captcha'];
        $codeKeys = ['code_enabled', 'default_code_driver'];
        if (array_intersect(array_keys($pending['user'] ?? []), $registrationKeys)
            || array_intersect(array_keys($pending['captcha'] ?? []), $codeKeys)) {
            // 注册策略和验证码服务在同一组锁内核验，空组只锁版本，不写入。
            foreach (['captcha', 'user'] as $group) {
                if (isset($pending[$group])) continue;
                $revision = ConfigService::getSnapshot($group)['revision'];
                if (!is_int($revision)) continue;
                $pending[$group] = [];
                $expected[$group] = $revision;
            }
        }

        $snapshots = ConfigService::compareAndSwapGroups($pending, $expected, function () use ($pending): void {
            // 取得所有版本锁之后复核跨字段规则，不能把锁前的组合当提交依据。
            if (isset($pending['redis'])) $this->validateRedisUpdate($pending['redis'], ConfigService::getGroup('redis'));
            $this->validateRegistrationUpdate($pending['user'] ?? [], $pending['captcha'] ?? []);
        });
        $result = [];
        foreach ($snapshots as $group => $snapshot) {
            if ($pending[$group] === []) continue;
            $result[$group] = $this->redactGroup($group, $snapshot['values'], $schemas[$group])
                + ['__revision' => $snapshot['revision']];
            if ($group === 'captcha') unset($result[$group]['code_channel']);
        }
        return $result;
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
        if (array_key_exists('__revision', $schema)) throw ApiException::badRequest('配置版本为保留元数据，不能注册为配置项');
        if ($group === 'redis') {
            throw ApiException::badRequest('Redis 配置定义由发行版管理');
        }
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
        if ($group === 'redis') throw ApiException::badRequest('Redis 系统配置组不能删除');
        ConfigService::deleteGroup($group);
    }

    public function deleteKey(string $group, string $key, GrantSet $grants): void
    {
        ConfigAccessPolicy::assertOperation($grants, 'config.deleteKey');
        ConfigAccessPolicy::assertGroup($grants, $group, '删除');
        if ($key === '__revision') throw ApiException::badRequest('配置版本为保留元数据，不能删除');
        if ($group === 'redis') throw ApiException::badRequest('Redis 系统配置项不能删除');
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

    private function validateRegistrationUpdate(array $user, array $captcha): void
    {
        $registration = array_intersect_key($user, array_flip(['registration_mode', 'registration_sms_verification', 'captcha']));
        if ($registration === [] && !array_key_exists('code_enabled', $captcha) && !array_key_exists('default_code_driver', $captcha)) return;
        if (array_key_exists('registration_mode', $registration) && !in_array($registration['registration_mode'], RegistrationPolicy::MODES, true)) {
            throw ApiException::validation(['user.registration_mode' => '请选择有效的注册方式。']);
        }
        foreach (['captcha', 'registration_sms_verification'] as $key) {
            if (array_key_exists($key, $registration) && !is_bool($registration[$key])) {
                throw ApiException::validation(['user.' . $key => '必须为布尔值。']);
            }
        }
        if (array_key_exists('code_enabled', $captcha) && !is_bool($captcha['code_enabled'])) {
            throw ApiException::validation(['captcha.code_enabled' => '必须为布尔值。']);
        }

        $saved = ConfigService::getGroup('user');
        $current = new RegistrationPolicy($saved);
        $next = new RegistrationPolicy(array_replace($saved, $registration));
        $services = array_replace(ConfigService::getGroup('captcha'), $captcha);
        foreach ($next->accountTypes() as $type) {
            if (!$next->requiresCode($type)) continue;
            $alreadyRequired = in_array($type, $current->accountTypes(), true) && $current->requiresCode($type);
            if ($alreadyRequired && !array_key_exists('code_enabled', $captcha) && !array_key_exists('default_code_driver', $captcha)) continue;
            if (!Captcha::codeAvailable(RegistrationPolicy::channel($type), (bool) ($services['code_enabled'] ?? true), (string) ($services['default_code_driver'] ?? 'smtp_code'))) {
                $channel = $type === 'email' ? '邮件' : '短信';
                throw ApiException::validation(['user.registration_mode' => $channel . '验证码服务未就绪，请检查验证服务与站外发送配置。'], $channel . '验证码服务未就绪', 'registration-code-unavailable');
            }
        }
    }

    private function validateRedisUpdate(array $changes, array $current): void
    {
        $next = array_merge($current, $changes);
        if (array_key_exists('enabled', $changes) && !is_bool($changes['enabled'])) {
            throw ApiException::validation(['redis.enabled' => ['必须为布尔值']]);
        }
        if (($next['enabled'] ?? false) === true) {
            try { DeploymentNamespace::current(); }
            catch (\InvalidArgumentException $exception) {
                throw ApiException::validation(['redis.enabled' => [$exception->getMessage()]], '部署身份未配置', 'deployment-identity-required');
            }
        }
        $host = trim((string) ($next['host'] ?? ''));
        $port = $next['port'] ?? null;
        $database = $next['database'] ?? null;
        if (($host === '' && ($next['enabled'] ?? false) === true)
            || strlen($host) > 253 || ($host !== '' && preg_match('/[\s\/:]/', $host) === 1)) {
            throw ApiException::validation(['redis.host' => ['Redis 地址无效']]);
        }
        if (filter_var($port, FILTER_VALIDATE_INT) === false || (int) $port < 1 || (int) $port > 65535) {
            throw ApiException::validation(['redis.port' => ['Redis 端口无效']]);
        }
        if (filter_var($database, FILTER_VALIDATE_INT) === false || (int) $database < 0 || (int) $database > 15) {
            throw ApiException::validation(['redis.database' => ['Redis 数据库编号必须在 0 到 15 之间']]);
        }
        if (array_key_exists('tls', $changes) && !is_bool($changes['tls'])) {
            throw ApiException::validation(['redis.tls' => ['必须为布尔值']]);
        }
        $tlsName = trim((string) ($next['tls_server_name'] ?? ''));
        if ($tlsName !== '' && RedisConnectionSettings::tlsIdentity(['host' => $host, 'tls_server_name' => $tlsName]) === null) {
            throw ApiException::validation(['redis.tls_server_name' => ['TLS 证书名称必须为 DNS 主机名']]);
        }
        if (($next['enabled'] ?? false) === true && ($next['tls'] ?? false) === true
            && RedisConnectionSettings::tlsIdentity(['host' => $host, 'tls_server_name' => $tlsName]) === null) {
            throw ApiException::validation(['redis.tls_server_name' => ['使用 IP 地址连接 TLS Redis 时必须填写证书名称']]);
        }
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
