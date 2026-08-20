<?php

namespace app\modules\Content\Likes\Transport\Api;

use think\Validate;

final class Likes extends Validate
{
    public static function requestSchemas(): array
    {
        return ['type' => ['type' => 'object', 'properties' => ['type' => ['type' => 'string', 'maxLength' => 32]], 'additionalProperties' => false]];
    }

    public static $all_scene = [
        'type' => ['normal' => ['type'], 'require' => false, 'nonNull' => false, 'toNull' => false],
    ];

    protected $rule = ['type' => 'max:32|alphaDash'];
}
