<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 发布选择器只暴露稳定编号与名称，不返回邮箱等账号资料。 */
final class SystemNoticeRecipientResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['id', 'username'], 'properties' => [
            'id' => ['type' => 'integer'], 'username' => ['type' => 'string'],
        ]];
    }
    public static function make($value): array
    {
        $row = (array) self::normalize($value);
        return ['id' => (int) $row['id'], 'username' => (string) $row['username']];
    }
}
