<?php

namespace app\shared\Transport\Validation;

use think\Validate;

final class ChannelTestInput extends Validate
{
    public static function requestSchemas(): array
    {
        return ['test' => ['type' => 'object', 'properties' => ['channel' => ['type' => 'string', 'maxLength' => 64], 'to' => ['type' => 'string', 'maxLength' => 320]], 'required' => ['channel'], 'additionalProperties' => false]];
    }

    public static $all_scene = ['test' => ['normal' => ['to'], 'require' => ['channel'], 'nonNull' => false, 'toNull' => false]];

    protected $rule = ['channel' => 'require|max:64|alphaDash', 'to' => 'max:320'];
}
