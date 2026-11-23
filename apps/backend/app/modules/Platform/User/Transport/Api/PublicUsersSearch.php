<?php

declare(strict_types=1);

namespace app\modules\Platform\User\Transport\Api;

use app\shared\Transport\Validation\PaginationInput;

/** 公开用户搜索只接受关键词和有界分页，不允许枚举空查询。 */
final class PublicUsersSearch extends PaginationInput
{
    public static $all_scene = [
        'search' => [
            'normal' => ['page', 'list_rows'],
            'require' => ['search_value'],
            'nonNull' => false,
            'toNull' => false,
        ],
    ];

    protected $rule = [
        'search_value' => 'length:1,100',
        'page' => 'integer|between:1,2147483647',
        'list_rows' => 'integer|between:1,30',
    ];

    protected $message = [
        'search_value.require' => '搜索内容不能为空',
        'search_value.length' => '搜索内容长度必须为 1-100 个字符',
        'page.number' => '页码格式错误',
        'page.between' => '页码范围错误',
        'list_rows.number' => '返回数量格式错误',
        'list_rows.between' => '返回数量范围必须为 1-30',
    ];

    public static function requestSchemas(): array
    {
        return [
            'search' => [
                'type' => 'object',
                'properties' => [
                    'search_value' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100],
                    'page' => ['type' => 'integer', 'minimum' => 1],
                    'list_rows' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
                ],
                'required' => ['search_value'],
                'additionalProperties' => false,
            ],
        ];
    }
}
