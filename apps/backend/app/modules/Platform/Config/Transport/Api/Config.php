<?php

namespace app\modules\Platform\Config\Transport\Api;

use think\Validate;

final class Config extends Validate
{
    public static function requestSchemas(): array
    {
        return [
            'list' => ['type' => 'object', 'properties' => ['group' => ['type' => 'string', 'maxLength' => 255]], 'additionalProperties' => false],
            'update' => ['type' => 'object', 'additionalProperties' => ['type' => 'object', 'properties' => ['__revision' => ['type' => 'integer', 'minimum' => 1, 'description' => '管理读取时的配置组版本，仅用于并发校验，不是业务配置']], 'required' => ['__revision'], 'additionalProperties' => true]],
            'register' => ['type' => 'object', 'properties' => ['group' => ['type' => 'string', 'maxLength' => 64], 'schema' => ['type' => 'object', 'additionalProperties' => true]], 'required' => ['group', 'schema'], 'additionalProperties' => false],
            'reload' => ['type' => 'object', 'properties' => ['group' => ['type' => 'string', 'maxLength' => 64]], 'additionalProperties' => false],
            'delete' => ['type' => 'object', 'properties' => ['group' => ['type' => 'string', 'maxLength' => 64]], 'required' => ['group'], 'additionalProperties' => false],
            'deleteKey' => ['type' => 'object', 'properties' => ['group' => ['type' => 'string', 'maxLength' => 64], 'key' => ['type' => 'string', 'maxLength' => 128]], 'required' => ['group', 'key'], 'additionalProperties' => false],
        ];
    }

    public static $all_scene = [
        'list' => ['normal' => ['group'], 'require' => false, 'nonNull' => false, 'toNull' => false],
        'update' => ['normal' => false, 'require' => false, 'nonNull' => false, 'toNull' => false, 'passthrough' => true],
        'register' => ['normal' => ['schema'], 'require' => ['group', 'schema'], 'nonNull' => false, 'toNull' => false],
        'reload' => ['normal' => ['group'], 'require' => false, 'nonNull' => false, 'toNull' => false],
        'delete' => ['normal' => false, 'require' => ['group'], 'nonNull' => false, 'toNull' => false],
        'deleteKey' => ['normal' => false, 'require' => ['group', 'key'], 'nonNull' => false, 'toNull' => false],
    ];

    protected $rule = [
        // config.list accepts a comma-separated group filter; the service
        // applies the authoritative allow-list for each individual group.
        // Requiredness belongs to `all_scene`. Keeping `require` in the
        // shared rule would make the optional config.list/reload group fail
        // even though BaseController correctly selects the current scene.
        'group' => 'max:255',
        'key' => 'max:128|alphaDash',
        'schema' => 'arrayJson',
    ];
}
