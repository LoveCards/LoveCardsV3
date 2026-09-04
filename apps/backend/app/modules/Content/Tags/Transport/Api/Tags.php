<?php

namespace app\modules\Content\Tags\Transport\Api;

use think\Validate;

class Tags extends Validate
{
    public static $all_scene = [
        'selfCreate' => [
            'normal' => [],
            'require' => ['name'],
            'nonNull' => false,
            'toNull' => false,
        ],
        'selfUpdate' => [
            'normal' => ['name'], 'require' => ['id'], 'nonNull' => false, 'toNull' => false,
        ],
        'adminUpdate' => [
            // Application and ownership are immutable administration boundaries.
            'normal' => ['name', 'status'],
            'require' => ['id'],
            'nonNull' => false,
            'toNull' => false,
        ],
        'create' => [
            'normal' => ['user_id'], 'require' => ['name'], 'nonNull' => false, 'toNull' => false,
        ],
    ];
    public static $scene_message = [
        'aid.require' => '应用ID不得为空',
        'name.require' => '标签名不得为空',
    ];

    protected $rule =   [
        'id'  => 'number',
        'aid'  => 'number',
        'user_id'  => 'number',
        'name'  => 'chsDash|length:1,255',
        'status'   => 'number|in:0,1,2,3',
    ];

    protected $message  =   [
        'aid.number'     => '应用ID格式错误',
        'user_id.number'     => '用户ID格式错误',

        'name.length'     => '标签名超出范围(1-255)',
        'name.chsDash' => '用户名只能为汉字、字母、数字下划线及破折号',

        'status.number'     => '状态格式错误',
    ];
}
