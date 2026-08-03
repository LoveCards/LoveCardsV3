<?php

namespace app\shared\Transport\Validation;

use think\Validate;

class BatchOperationInput extends Validate
{
    static public $all_scene = [
        'SingleOperate' => [
            'normal' => false,
            'require' => ['id'],
            'nonNull' => false,
            'toNull' => false,
        ],
        'BatchOperate' => [
            'normal' => false,
            'require' => [
                'ids',
                'method'
            ],
            'nonNull' => false,
            'toNull' => false,
        ],
    ];

    static public $scene_message = [
        'ids.require' => 'ID集不能为空',
        'method.require' => '方法不能为空',
    ];

    protected $rule = [
        'id'  => 'number',
        'ids'  => 'arrayJson',
        'method'  => 'alphaDash',
    ];

    protected $message = [
        'ids.arrayJson' => 'ID集格式错误',
        'method.alpha' => '方法格式错误',
    ];
}
