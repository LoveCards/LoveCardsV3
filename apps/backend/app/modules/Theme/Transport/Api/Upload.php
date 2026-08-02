<?php

namespace app\modules\Theme\Transport\Api;

use think\Validate;

/** Request contract for theme ZIP uploads. */
final class Upload extends Validate
{
    protected $rule = [
        'file' => 'require',
    ];

    protected $message = [
        'file.require' => '主题 ZIP 文件不能为空',
    ];
}
