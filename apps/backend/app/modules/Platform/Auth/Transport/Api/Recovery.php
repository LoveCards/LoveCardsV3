<?php

namespace app\modules\Platform\Auth\Transport\Api;

use think\Validate;

final class Recovery extends Validate
{
    public static function requestSchemas(): array
    {
        return [
            'request' => ['type' => 'object', 'properties' => ['account' => ['type' => 'string', 'maxLength' => 320]], 'required' => ['account'], 'additionalProperties' => false],
            'reset' => ['type' => 'object', 'properties' => [
                'challenge_id' => ['type' => 'integer', 'minimum' => 1],
                'code' => ['type' => 'string', 'maxLength' => 128],
                'password' => ['type' => 'string', 'minLength' => 8, 'maxLength' => 36],
            ], 'required' => ['challenge_id', 'code', 'password'], 'additionalProperties' => false],
        ];
    }

    public static $all_scene = [
        'request' => ['normal' => false, 'require' => ['account'], 'nonNull' => false, 'toNull' => false],
        'reset' => ['normal' => false, 'require' => ['challenge_id', 'code', 'password'], 'nonNull' => false, 'toNull' => false],
    ];

    protected $rule = [
        'account' => 'require|max:320',
        'challenge_id' => 'require|number',
        'code' => 'require|max:128',
        'password' => 'require|length:8,36|password',
    ];
}
