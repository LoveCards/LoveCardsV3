<?php

namespace app\modules\Platform\Library\Transport\Api;

use think\Validate;

/** Validates a stable resource reference used by library mutations. */
final class Library extends Validate
{
    public static function requestSchemas(): array
    {
        return [
            'resource' => [
                'type' => 'object',
                'properties' => [
                    'resource_type' => [
                        'type' => 'string',
                        'maxLength' => 32,
                        'pattern' => '^[a-z][a-z0-9_]{0,31}$',
                    ],
                    'resource_id' => [
                        'type' => 'string',
                        'maxLength' => 128,
                        'pattern' => '^[A-Za-z0-9][A-Za-z0-9._:-]*$',
                    ],
                ],
                'required' => ['resource_type', 'resource_id'],
                'additionalProperties' => false,
            ],
        ];
    }

    public static $all_scene = [
        'resource' => [
            'normal' => ['resource_type', 'resource_id'],
            'require' => ['resource_type', 'resource_id'],
            'nonNull' => false,
            'toNull' => false,
        ],
    ];

    protected $rule = [
        'resource_type' => 'require|regex:/^[a-z][a-z0-9_]{0,31}$/',
        'resource_id' => 'require|max:128|regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/',
    ];
}
