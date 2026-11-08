<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 策略 CAS 修改结果：新版本号 + 全量现行规则。 */
final class AdminNotificationPoliciesApplyResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        $schema = AdminNotificationPoliciesResource::schema($variant);
        $schema['properties']['revision'] = ['type' => 'integer'];
        return $schema;
    }

    public static function make($value): array
    {
        $data = AdminNotificationPoliciesResource::make($value);
        $data['revision'] = (int) ($data['revision'] ?? 0);
        return $data;
    }
}
