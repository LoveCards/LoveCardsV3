<?php

namespace app\modules\Theme\Transport\Api;

use think\Validate;

final class Admin extends Validate
{
    public static function requestSchemas(): array
    {
        return [
            'activate' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'release' => ['type' => 'string']], 'required' => ['id', 'release'], 'additionalProperties' => false],
            'delete' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'release' => ['type' => 'string']], 'required' => ['id', 'release'], 'additionalProperties' => false],
            'updateConfig' => ['type' => 'object', 'properties' => [
                'id' => ['type' => 'string'], 'release' => ['type' => 'string'], 'values' => ['type' => 'object', 'additionalProperties' => true],
            ], 'required' => ['id', 'release', 'values'], 'additionalProperties' => false],
        ];
    }
    public static $all_scene = [
        'activate' => ['normal' => false, 'require' => ['id', 'release'], 'nonNull' => false, 'toNull' => false],
        'delete' => ['normal' => false, 'require' => ['id', 'release'], 'nonNull' => false, 'toNull' => false],
        'updateConfig' => ['normal' => false, 'require' => ['id', 'release', 'values'], 'nonNull' => false, 'toNull' => false],
    ];
    protected $rule = ['id' => 'alphaDash|max:64', 'release' => 'regex:/^[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?-[a-f0-9]{16}$/', 'values' => 'arrayJson'];
}
