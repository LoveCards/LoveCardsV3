<?php

namespace app\modules\Platform\Files\Transport\Api;

use think\Validate;

class Upload extends Validate
{
    public static $all_scene = [
        'CheckImage' => [
            'normal' => ['file'],
            'require' => ['file'],
            'nonNull' => false,
            'toNull' => false,
        ],
        'CheckUpload' => [
            'normal' => ['aid', 'pid', 'file'],
            'require' => ['aid', 'pid', 'file'],
            'nonNull' => false,
            'toNull' => false,
        ],
    ];

    public static $scene_message = [
        'file.require' => '文件不得为空',
    ];

    // 尺寸与扩展名限额已迁至 Application/UploadLimits（按上传场景分组），
    // 校验层只保留协议级校验，不再读取业务配置。

    protected $rule = [
        'aid' => 'require|number',
        'pid' => 'require|number',
        'uid' => 'require|number',
        'url' => 'require',
        'file' => 'require',
    ];

    protected $message = [
        'aid.require' => 'aid不得为空',
        'aid.number' => 'aid格式错误',

        'pid.require' => 'pid不得为空',
        'pid.number' => 'pid格式错误',

        'uid.require' => 'uid不得为空',
        'uid.number' => 'uid格式错误',

        'url.require' => 'url不得为空',

        'file.require' => '文件不得为空',
        'file.fileSize' => '文件超出上传限制',
        'file.fileExt' => '文件格式错误',
    ];
}
