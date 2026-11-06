<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 运行时配置校验结果（无副作用）。 */
final class AdminRuntimeValidateResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'valid' => ['type' => 'boolean'],
            'violations' => ['type' => 'array'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        if (!is_array($data)) return [];
        $data['violations'] = array_values(is_array($data['violations'] ?? null) ? $data['violations'] : []);
        return $data;
    }
}
