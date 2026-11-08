<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use think\Validate;

/** 审计列表专用查询，避免通用管理列表接受无效筛选字段。 */
final class AdminAuditQueryValidate extends Validate
{
    public static $all_scene = [
        'list' => [
            'normal' => ['page', 'list_rows', 'action', 'actor', 'actor_id', 'resource_type', 'resource_id', 'request_id', 'correlation_id', 'from', 'to'],
            'require' => false, 'nonNull' => false, 'toNull' => [],
        ],
    ];

    protected $rule = [
        'page' => 'number|between:1,1000000',
        'list_rows' => 'number|between:1,100',
        'action' => 'max:128|regex:/^[A-Za-z][A-Za-z0-9._:-]*$/',
        'actor' => ['max:10', 'regex:/^(?:system|[1-9][0-9]{0,9})$/'],
        'actor_id' => 'number|between:1,2147483647',
        'resource_type' => 'max:64|regex:/^[A-Za-z][A-Za-z0-9._:-]*$/',
        'resource_id' => 'max:128',
        'request_id' => 'max:128',
        'correlation_id' => 'max:128',
        'from' => 'dateFormat:Y-m-d H:i:s',
        'to' => 'dateFormat:Y-m-d H:i:s',
    ];
}
