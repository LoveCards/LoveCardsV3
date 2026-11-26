<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use think\Validate;

/** 管理端查询和治理动作的请求场景。 */
final class AdminValidate extends Validate
{
    public static function requestSchemas(): array
    {
        $pagination = ['page' => ['type' => 'number'], 'list_rows' => ['type' => 'number']];
        return [
            'eventList' => ['type' => 'object', 'properties' => $pagination + [
                'event_id' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 9007199254740991],
                'event_type' => ['type' => 'string', 'maxLength' => 100],
                'queue_name' => ['type' => 'string', 'maxLength' => 64],
            ], 'additionalProperties' => false],
            'reportList' => ['type' => 'object', 'properties' => $pagination + [
                'status' => ['type' => 'string', 'maxLength' => 32],
                'status_group' => ['type' => 'string', 'enum' => ['pending'], 'description' => '待办举报：待处理和处理中；不能与 status 同时使用。'],
                'resource_type' => ['type' => 'string', 'maxLength' => 64],
                'search_value' => ['type' => 'string', 'maxLength' => 255],
            ], 'not' => ['required' => ['status', 'status_group']], 'additionalProperties' => false],
        ];
    }

    public static $all_scene = [
        'pagination' => [
            'normal' => ['page', 'list_rows', 'search_value', 'search_keys', 'status', 'resource_type', 'actor_id', 'unread_only', 'worker_type'],
            'require' => false, 'nonNull' => false, 'toNull' => [],
        ],
        'deliveryList' => [
            'normal' => ['page', 'list_rows', 'status', 'worker_type', 'queue_name', 'policy_decision'],
            'require' => false, 'nonNull' => false, 'toNull' => [],
        ],
        'eventList' => [
            'normal' => ['page', 'list_rows', 'event_id', 'event_type', 'queue_name'],
            'require' => false, 'nonNull' => false, 'toNull' => [],
        ],
        'reportList' => [
            'normal' => ['page', 'list_rows', 'search_value', 'status', 'status_group', 'resource_type'],
            'require' => false, 'nonNull' => false, 'toNull' => [],
        ],
        'transition' => [
            'normal' => ['note', 'resolution'],
            'require' => ['status'], 'nonNull' => false, 'toNull' => [],
        ],
        'migrationRun' => [
            'normal' => [],
            'require' => false, 'nonNull' => false, 'toNull' => [],
        ],
        'upgradeList' => [
            'normal' => ['limit'], 'require' => false, 'nonNull' => false, 'toNull' => [],
        ],
        'upgradeCreate' => [
            'normal' => [], 'require' => ['file'], 'nonNull' => false, 'toNull' => [],
        ],
        // 通信运行时控制面：字段级范围校验在 Runtime Domain（结构化 422），
        // 这里只做类型与存在性防线。
        'runtimeConfig' => [
            'normal' => ['desired_mode', 'expected_revision', 'notification_interval_seconds', 'message_interval_seconds',
                'unread_interval_seconds', 'pause_when_hidden',
                'worker_stale_seconds', 'scheduler_stale_seconds', 'gateway_stale_seconds'],
            'require' => ['desired_mode', 'expected_revision'], 'nonNull' => false, 'toNull' => [],
        ],
        'eventConsumption' => [
            'normal' => ['scheduled_trigger'], 'require' => ['mode', 'expected_revision'], 'nonNull' => false, 'toNull' => [],
        ],
        // 通知策略 CAS 修改：结构化校验在 ManageNotificationPolicies（契约 §5.2）。
        'notificationPolicies' => [
            'normal' => ['reason'], 'require' => ['expected_revision', 'policies'], 'nonNull' => false, 'toNull' => [],
        ],
        'signalPolicies' => [
            'normal' => [], 'require' => ['expected_revision', 'signals'], 'nonNull' => false, 'toNull' => [],
        ],
    ];

    protected $rule = [
        'expected_revision' => 'number',
        'policies' => 'array',
        'signals' => 'array',
        'reason' => 'max:255',
        'page' => 'number',
        'list_rows' => 'number',
        'actor_id' => 'number',
        'status' => 'max:32',
        'status_group' => 'in:pending|reportStatusGroup',
        'queue_name' => 'max:64',
        'policy_decision' => 'in:applied,skipped,not-configured',
        'resource_type' => 'max:64',
        'worker_type' => 'max:64',
        'search_value' => 'max:255',
        'search_keys' => 'max:255',
        'note' => 'max:2000',
        'resolution' => 'max:255',
        'limit' => 'number|between:1,200',
        'file' => 'require|fileExt:zip',
        'unread_only' => 'boolean',
        'event_type' => 'max:100',
        'event_id' => 'integer|regex:/^[1-9][0-9]*$/|between:1,9007199254740991',
        'desired_mode' => 'max:16',
        'mode' => 'in:scheduled,resident',
        'scheduled_trigger' => 'in:none,cli,http',
        'expected_revision' => 'number',
        'notification_interval_seconds' => 'number',
        'message_interval_seconds' => 'number',
        'unread_interval_seconds' => 'number',
        'pause_when_hidden' => 'boolean',
        'worker_stale_seconds' => 'number',
        'scheduler_stale_seconds' => 'number',
        'gateway_stale_seconds' => 'number',
    ];

    /** 单一状态和待办分组是两种互斥筛选，不能悄悄取交集。 */
    protected function reportStatusGroup($value, $rule, array $data): bool|string
    {
        return array_key_exists('status', $data) ? '状态分组不能与单个状态同时使用' : true;
    }

    protected $message = [
        'status.require' => '状态不能为空',
        'status_group.in' => '举报状态分组无效',
        'event_id.integer' => '事件 ID 必须为正整数',
        'event_id.between' => '事件 ID 超出有效范围',
        'file.require' => 'Release ZIP 不能为空',
        'file.fileExt' => 'Release 文件必须是 ZIP',
        'page.number' => '页码格式错误',
        'list_rows.number' => '每页数量格式错误',
        'unread_only.boolean' => '未读筛选格式错误',
    ];
}
