<?php
declare(strict_types=1);

namespace app\modules\Platform\Config\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 只供已认证 Gateway 读取的 Redis 连接配置。 */
final class InternalRedisConfigResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false,
            'required' => ['enabled', 'host', 'port', 'database', 'password', 'tls', 'tls_server_name', 'source', 'revision'],
            'properties' => [
                'enabled' => ['type' => 'boolean'],
                'host' => ['type' => 'string'],
                'port' => ['type' => 'integer'],
                'database' => ['type' => 'integer'],
                'password' => ['type' => 'string'],
                'tls' => ['type' => 'boolean'],
                'tls_server_name' => ['type' => 'string'],
                'source' => ['type' => 'string', 'enum' => ['env', 'database']],
                'revision' => ['type' => 'string'],
            ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        return is_array($data) ? $data : [];
    }
}
