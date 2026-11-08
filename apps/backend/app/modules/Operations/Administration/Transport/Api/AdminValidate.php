<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use think\Validate;

/** 管理端查询和治理动作的请求场景。 */
final class AdminValidate extends Validate
{
    public static $all_scene = [
        'pagination' => [
            'normal' => ['page', 'list_rows', 'search_value', 'search_keys', 'status', 'resource_type', 'actor_id', 'unread_only', 'worker_type'],
            'require' => false, 'nonNull' => false, 'toNull' => [],
        ],
        'deliveryList' => [
            'normal' => ['page', 'list_rows', 'status', 'worker_type', 'queue_name', 'policy_decision'],
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
                'unread_interval_seconds', 'polling_backoff_factor', 'polling_max_interval_seconds', 'pause_when_hidden',
                'worker_stale_seconds', 'scheduler_stale_seconds', 'gateway_stale_seconds', 'require_supervisor'],
            'require' => ['desired_mode', 'expected_revision'], 'nonNull' => false, 'toNull' => [],
        ],
        // 通知策略 CAS 修改：结构化校验在 ManageNotificationPolicies（契约 §5.2）。
        'notificationPolicies' => [
            'normal' => ['reason'], 'require' => ['expected_revision', 'policies'], 'nonNull' => false, 'toNull' => [],
        ],
    ];

    protected $rule = [
        'expected_revision' => 'number',
        'policies' => 'array',
        'reason' => 'max:255',
        'page' => 'number',
        'list_rows' => 'number',
        'actor_id' => 'number',
        'status' => 'max:32',
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
        'desired_mode' => 'max:16',
        'expected_revision' => 'number',
        'notification_interval_seconds' => 'number',
        'message_interval_seconds' => 'number',
        'unread_interval_seconds' => 'number',
        'polling_backoff_factor' => 'number',
        'polling_max_interval_seconds' => 'number',
        'pause_when_hidden' => 'boolean',
        'worker_stale_seconds' => 'number',
        'scheduler_stale_seconds' => 'number',
        'gateway_stale_seconds' => 'number',
        'require_supervisor' => 'boolean',
    ];

    protected $message = [
        'status.require' => '状态不能为空',
        'file.require' => 'Release ZIP 不能为空',
        'file.fileExt' => 'Release 文件必须是 ZIP',
        'page.number' => '页码格式错误',
        'list_rows.number' => '每页数量格式错误',
        'unread_only.boolean' => '未读筛选格式错误',
    ];
}
