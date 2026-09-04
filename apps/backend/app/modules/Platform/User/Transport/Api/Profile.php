<?php

namespace app\modules\Platform\User\Transport\Api;

use think\Validate;

final class Profile extends Validate
{
    public static function requestSchemas(): array
    {
        return [
            'update' => [
                'type' => 'object',
                'x-typescript-type' => 'ProfileUpdateParams',
                'properties' => [
                    'avatar' => ['type' => 'string', 'format' => 'uri', 'maxLength' => 255],
                    'username' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 12],
                    'bio' => ['type' => 'string', 'nullable' => true, 'maxLength' => 160],
                    'location' => ['type' => 'string', 'nullable' => true, 'maxLength' => 64],
                    'gender' => ['type' => 'string', 'nullable' => true, 'enum' => ['male', 'female', 'non_binary', 'prefer_not_to_say']],
                    'birth_date' => ['type' => 'string', 'nullable' => true, 'format' => 'date'],
                    'cover_file_id' => ['type' => 'integer', 'minimum' => 1, 'nullable' => true],
                ],
                'additionalProperties' => false,
            ],
            'password' => [
                'type' => 'object',
                'x-typescript-type' => 'PasswordParams',
                'properties' => [
                    'current_password' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 36],
                    'new_password' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 36],
                ],
                'required' => ['current_password', 'new_password'],
                'additionalProperties' => false,
            ],
            'email' => [
                'type' => 'object',
                'x-typescript-type' => 'EmailParams',
                'properties' => [
                    'current_password' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 36],
                    'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 320],
                    'captcha' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
                ],
                'required' => ['current_password', 'email', 'captcha'],
                'additionalProperties' => false,
            ],
            'emailCaptcha' => [
                'type' => 'object',
                'x-typescript-type' => 'EmailCaptchaParams',
                'properties' => ['email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 320]],
                'required' => ['email'],
                'additionalProperties' => false,
            ],
        ];
    }

    public static $all_scene = [
        'update' => ['normal' => ['avatar', 'username', 'bio', 'location', 'gender', 'birth_date', 'cover_file_id'], 'require' => [], 'nonNull' => false, 'toNull' => false],
        'password' => ['normal' => false, 'require' => ['current_password', 'new_password'], 'nonNull' => false, 'toNull' => false],
        'email' => ['normal' => false, 'require' => ['current_password', 'email', 'captcha'], 'nonNull' => false, 'toNull' => false],
        'emailCaptcha' => ['normal' => ['email'], 'require' => ['email'], 'nonNull' => false, 'toNull' => false],
    ];

    protected $rule = [
        'avatar' => 'max:255|url',
        'username' => 'length:3,12|chsDash',
        'current_password' => 'length:5,36',
        'new_password' => 'length:5,36|password',
        'email' => 'max:320|email',
        'captcha' => 'require|max:64',
        'bio' => 'max:160',
        'location' => 'max:64|regex:/^[^\r\n]*$/u',
        'gender' => 'in:male,female,non_binary,prefer_not_to_say',
        'birth_date' => 'dateFormat:Y-m-d',
        'cover_file_id' => 'number|gt:0',
    ];
}
