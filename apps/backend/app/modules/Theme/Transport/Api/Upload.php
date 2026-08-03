<?php

namespace app\modules\Theme\Transport\Api;

use think\Validate;

/** Request contract for theme ZIP uploads. */
final class Upload extends Validate
{
    public static $all_scene = [
        'default' => [
            'normal' => ['file'],
            'require' => ['file'],
            'nonNull' => false,
            'toNull' => false,
        ],
    ];

    protected $rule = [
        'file' => 'require',
    ];

    protected $message = [
        'file.require' => '主题 ZIP 文件不能为空',
    ];
}
