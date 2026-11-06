<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 策略 CAS 修改结果：新版本号 + 全量现行规则。 */
final class AdminNotificationPoliciesApplyResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['revision', 'policies'], 'properties' => [
            'revision' => ['type' => 'integer'],
            'policies' => ['type' => 'array'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        if (!is_array($data)) return ['revision' => 0, 'policies' => []];
        return [
            'revision' => (int) ($data['revision'] ?? 0),
            'policies' => array_values(is_array($data['policies'] ?? null) ? $data['policies'] : []),
        ];
    }
}
