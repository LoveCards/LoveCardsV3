<?php

namespace app\modules\Platform\Config\Service;

use app\api\ApiException;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\Scope;

/** 配置管理入口的组级授权与敏感字段规则。 */
final class ConfigAccessPolicy
{
    private const GROUP_CAPABILITIES = [
        'redis' => 'operations.runtime.configure',
        'storage' => 'storage.read',
        'sender' => 'sender.read',
        'captcha' => 'captcha.read',
    ];

    public static function assertOperation(GrantSet $grants, string $capability): void
    {
        if (!$grants->allows('admin.access', Scope::GLOBAL)
            || !$grants->allows($capability, Scope::GLOBAL)) {
            throw ApiException::forbidden('无权执行配置管理操作');
        }
    }

    public static function canAccessGroup(GrantSet $grants, string $group): bool
    {
        $capability = self::groupCapability($group);
        return $capability === null || $grants->allows($capability, Scope::GLOBAL);
    }

    public static function assertGroup(GrantSet $grants, string $group, string $operation): void
    {
        if (!self::canAccessGroup($grants, $group)) {
            throw ApiException::forbidden("无权{$operation}配置组 {$group}");
        }
    }

    public static function isSensitive(string $group, string $key, array $definition = []): bool
    {
        $type = strtolower((string) ($definition['type'] ?? ''));
        if (($definition['sensitive'] ?? false) === true || in_array($type, ['password', 'secret'], true)) {
            return true;
        }

        $normalizedKey = strtolower(trim($key));
        if (preg_match('/(?:^|_)(?:password|passwd|pass|pwd|secret|token|private_key|api_key|access_key|credential)(?:$|_)/', $normalizedKey)) {
            return true;
        }

        return str_starts_with(strtolower(trim($group)), 'captcha_') && $normalizedKey === 'key';
    }

    public static function secretMarker($value): array
    {
        return [
            '$secret' => 'keep',
            'configured' => $value !== null && $value !== '',
        ];
    }

    private static function groupCapability(string $group): ?string
    {
        $normalized = strtolower(trim($group));
        foreach (self::GROUP_CAPABILITIES as $prefix => $capability) {
            if ($normalized === $prefix || str_starts_with($normalized, $prefix . '_')) {
                return $capability;
            }
        }
        return null;
    }
}
