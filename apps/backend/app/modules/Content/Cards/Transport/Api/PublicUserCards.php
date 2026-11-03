<?php

namespace app\modules\Content\Cards\Transport\Api;

use think\Validate;

/** Request contract for the public card stream of one user. */
final class PublicUserCards extends Validate
{
    public static function requestSchemas(): array
    {
        return [
            'list' => [
                'type' => 'object',
                'properties' => [
                    'page' => ['type' => 'integer', 'minimum' => 1],
                    'list_rows' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ],
                'additionalProperties' => false,
            ],
        ];
    }

    public static $all_scene = [
        'list' => [
            'normal' => ['page', 'list_rows'],
            'require' => false,
            'nonNull' => false,
            'toNull' => false,
        ],
    ];

    protected $rule = [
        'page' => 'number|between:1,2147483647',
        'list_rows' => 'number|between:1,100',
    ];

    protected $message = [
        'page.number' => '页码格式错误',
        'page.between' => '页码范围必须为 1-2147483647',
        'list_rows.number' => '每页数量格式错误',
        'list_rows.between' => '每页数量范围必须为 1-100',
    ];
}
