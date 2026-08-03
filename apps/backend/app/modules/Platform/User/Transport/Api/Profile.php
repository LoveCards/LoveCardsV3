<?php

namespace app\modules\Platform\User\Transport\Api;

use think\Validate;

final class Profile extends Validate
{
    public static $all_scene = [
        'update' => ['normal' => ['avatar', 'username', 'password'], 'require' => [], 'nonNull' => false, 'toNull' => false],
        'password' => ['normal' => ['password'], 'require' => ['password'], 'nonNull' => false, 'toNull' => false],
        'email' => ['normal' => ['email', 'captcha'], 'require' => ['email'], 'nonNull' => false, 'toNull' => false],
        'emailCaptcha' => ['normal' => ['email'], 'require' => ['email'], 'nonNull' => false, 'toNull' => false],
    ];

    protected $rule = [
        'avatar' => 'max:255|url',
        'username' => 'length:3,12|chsDash',
        'password' => 'length:5,36|password',
        'email' => 'max:320|email',
        'captcha' => 'max:64',
    ];
}
