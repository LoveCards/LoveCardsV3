<?php
declare(strict_types=1);

namespace app\modules\Platform\Config\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 管理端连接测试只返回结果与配置来源，不返回凭据。 */
final class RedisConnectionTestResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false,
            'required' => ['ready', 'reason_code', 'source'],
            'properties' => [
                'ready' => ['type' => 'boolean'],
                'reason_code' => ['type' => ['string', 'null']],
                'source' => ['type' => 'string', 'enum' => ['env', 'database']],
            ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        return [
            'ready' => (bool) ($data['ready'] ?? false),
            'reason_code' => isset($data['reason_code']) ? (string) $data['reason_code'] : null,
            'source' => (string) ($data['source'] ?? 'database'),
        ];
    }
}
