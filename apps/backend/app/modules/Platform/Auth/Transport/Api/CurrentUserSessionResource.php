<?php
declare(strict_types=1);

namespace app\modules\Platform\Auth\Transport\Api;

use app\shared\Transport\Http\Resource;

final class CurrentUserSessionResource extends Resource
{
    public static function make($value): array
    {
        $data = self::normalize($value);
        return [
            'id' => (string) ($data['id'] ?? ''),
            'current' => (bool) ($data['current'] ?? false),
            'status' => (string) ($data['status'] ?? 'expired'),
            'created_at' => (string) ($data['created_at'] ?? ''),
            'expires_at' => (string) ($data['expires_at'] ?? ''),
            'last_seen_at' => self::nullable($data['last_seen_at'] ?? null),
            'device_name' => self::nullable($data['device_name'] ?? null),
            'ip_address' => self::nullable($data['ip_address'] ?? null),
            'location' => self::nullable($data['location'] ?? null),
        ];
    }

    public static function schema(string $variant = 'default'): array
    {
        $nullable = ['type' => 'string', 'nullable' => true];
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['id','current','status','created_at','expires_at','last_seen_at','device_name','ip_address','location'],
            'properties' => [
                'id' => ['type' => 'string'], 'current' => ['type' => 'boolean'],
                'status' => ['type' => 'string', 'enum' => ['active','revoked','expired']],
                'created_at' => ['type' => 'string'], 'expires_at' => ['type' => 'string'],
                'last_seen_at' => $nullable, 'device_name' => $nullable, 'ip_address' => $nullable, 'location' => $nullable,
            ],
        ];
    }

    private static function nullable(mixed $value): ?string { return $value === null || $value === '' ? null : (string) $value; }
}
