<?php

namespace app\shared\Transport\Validation;

use think\Validate;

/** 通知、消息和历史等持续增长列表的游标输入。 */
final class CursorInput extends Validate
{
    public static $all_scene = [
        'default' => [
            'normal' => ['cursor', 'limit'],
            'require' => false,
            'nonNull' => false,
        ],
    ];

    protected $rule = [
        'cursor' => 'alphaDash|max:128',
        'limit' => 'number|between:1,100',
    ];

    protected $message = [
        'cursor.alphaDash' => '游标格式错误',
        'cursor.max' => '游标长度超出限制',
        'limit.number' => '列表长度格式错误',
        'limit.between' => '列表长度范围错误(1-100)',
    ];
}
