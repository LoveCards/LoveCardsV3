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
    ];

    protected $rule = [
        'page' => 'number',
        'list_rows' => 'number',
        'actor_id' => 'number',
        'status' => 'max:32',
        'resource_type' => 'max:64',
        'worker_type' => 'max:64',
        'search_value' => 'max:255',
        'search_keys' => 'max:255',
        'note' => 'max:2000',
        'resolution' => 'max:255',
        'limit' => 'number|between:1,200',
        'file' => 'require|fileExt:zip',
        'unread_only' => 'boolean',
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
