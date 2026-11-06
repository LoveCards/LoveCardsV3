<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 通知策略目录响应：当前全局版本 + 现行规则行（契约 §5）。 */
final class AdminNotificationPoliciesResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['revision', 'policies'], 'properties' => [
            'revision' => ['type' => ['integer', 'null']],
            'policies' => ['type' => 'array'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        if (!is_array($data)) return ['revision' => null, 'policies' => []];
        return [
            'revision' => isset($data['revision']) ? (int) $data['revision'] : null,
            'policies' => array_values(is_array($data['policies'] ?? null) ? $data['policies'] : []),
        ];
    }
}
