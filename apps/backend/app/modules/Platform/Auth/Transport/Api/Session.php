<?php

namespace app\modules\Platform\Auth\Transport\Api;

use think\Validate;

final class Session extends Validate
{
    public static $all_scene = [
        'login' => ['normal' => ['account', 'password'], 'require' => ['account', 'password'], 'nonNull' => false, 'toNull' => false],
        'register' => ['normal' => ['account', 'password', 'code', 'captcha_id', 'captcha_code', 'lot_number', 'captcha_output', 'pass_token', 'gen_time'], 'require' => ['account', 'password'], 'nonNull' => false, 'toNull' => false],
        'captcha' => ['normal' => ['account'], 'require' => ['account'], 'nonNull' => false, 'toNull' => false],
    ];

    protected $rule = [
        'account' => 'max:320',
        'password' => 'length:5,36|password',
        'code' => 'max:64',
        'captcha_id' => 'max:128',
        'captcha_code' => 'max:64',
        'lot_number' => 'max:128',
        'captcha_output' => 'max:512',
        'pass_token' => 'max:512',
        'gen_time' => 'max:64',
    ];
}
