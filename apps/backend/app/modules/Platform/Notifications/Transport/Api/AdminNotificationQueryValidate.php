<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Transport\Api;

use think\Validate;

/** 列表按生成时间及编号倒序；不接受任意列排序或通知正文筛选。 */
final class AdminNotificationQueryValidate extends Validate
{
    public static $all_scene = [
        'list' => [
            'normal' => ['page', 'list_rows', 'recipient_id', 'type', 'read_state', 'from', 'to'],
            'require' => false, 'nonNull' => false, 'toNull' => [],
        ],
    ];

    // 框架默认跳过空值。让可选字段的自定义规则区分“未提供”和“提供空值”。
    protected $must = ['page', 'list_rows', 'recipient_id', 'type', 'read_state', 'from', 'to'];
    protected $rule = [
        'page' => 'optionalInteger:1000000',
        'list_rows' => 'optionalInteger:100',
        'recipient_id' => 'optionalInteger:2147483647',
        'type' => 'storedType',
        'read_state' => 'readState',
        'from' => 'storageTimestamp',
        'to' => ['storageTimestamp', 'rangeEnd'],
    ];
    protected $message = [
        'page' => '页码必须是 1 至 1000000 的整数',
        'list_rows' => '每页条数必须是 1 至 100 的整数',
        'recipient_id' => '接收用户编号必须是有效的正整数',
        'type' => '通知类型必须是 1 至 64 个字符的字符串',
        'read_state' => '阅读状态必须是 read 或 unread',
        'from' => '开始时间必须为有效的 YYYY-MM-DD HH:mm:ss',
        'to.storageTimestamp' => '结束时间必须为有效的 YYYY-MM-DD HH:mm:ss',
        'to.rangeEnd' => '结束时间必须晚于开始时间',
    ];

    public static function requestSchemas(): array
    {
        $timestamp = ['type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$'];
        return ['list' => [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000000, 'default' => 1],
                'list_rows' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                'recipient_id' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'type' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64, 'description' => '按存储类型精确筛选，支持未知历史值；省略时包含所有类型'],
                'read_state' => ['type' => 'string', 'enum' => ['read', 'unread']],
                'from' => $timestamp + ['description' => '服务端保存时间，含边界；不进行时区转换'],
                'to' => $timestamp + ['description' => '服务端保存时间，不含边界；必须晚于 from'],
            ],
        ]];
    }

    protected function optionalInteger($value, $rule, array $data, string $field): bool
    {
        if (!array_key_exists($field, $data)) return true;
        return (is_int($value) || is_string($value))
            && preg_match('/^[1-9][0-9]*$/D', (string) $value) === 1
            && $value <= (int) $rule;
    }

    protected function storedType($value, $rule, array $data, string $field): bool
    {
        if (!array_key_exists($field, $data)) return true;
        return is_string($value) && mb_strlen($value, 'UTF-8') >= 1
            && mb_strlen($value, 'UTF-8') <= 64 && !str_contains($value, "\0");
    }

    protected function readState($value, $rule, array $data, string $field): bool
    {
        return !array_key_exists($field, $data) || in_array($value, ['read', 'unread'], true);
    }

    protected function storageTimestamp($value, $rule, array $data, string $field): bool
    {
        if (!array_key_exists($field, $data)) return true;
        if (!is_string($value) || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $value) !== 1) return false;
        // UTC 仅避免日历合法性检查受到 DST 干扰，不声明数据库值的时区。
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d H:i:s') === $value && (int) substr($value, 0, 4) >= 1000;
    }

    protected function rangeEnd($value, $rule, array $data, string $field): bool
    {
        if (!isset($data['from'], $data['to']) || !is_string($data['from']) || !is_string($data['to'])) return true;
        return strcmp($data['from'], $data['to']) < 0;
    }
}
