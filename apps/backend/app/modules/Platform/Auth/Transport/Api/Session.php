<?php

namespace app\modules\Platform\Auth\Transport\Api;

use think\Validate;

final class Session extends Validate
{
    public static $all_scene = [
        'login' => ['normal' => ['account', 'password'], 'require' => ['account', 'password'], 'nonNull' => false, 'toNull' => false],
        'register' => ['normal' => ['account', 'password', 'code'], 'require' => ['account', 'password'], 'nonNull' => false, 'toNull' => false],
        'captcha' => ['normal' => ['account'], 'require' => ['account'], 'nonNull' => false, 'toNull' => false],
    ];

    protected $rule = [
        'account' => 'max:320',
        'password' => 'length:5,36|password',
        'code' => 'max:64',
    ];
}
