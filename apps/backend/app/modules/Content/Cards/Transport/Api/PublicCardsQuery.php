<?php

namespace app\modules\Content\Cards\Transport\Api;

use app\shared\Transport\Validation\PaginationInput;

/** 公开卡片列表与搜索共用的分页、搜索及排序请求契约。 */
final class PublicCardsQuery extends PaginationInput
{
    public static $all_scene = [
        'list' => [
            'normal' => ['page', 'list_rows', 'search_keys', 'search_like', 'order_desc', 'order_key', 'show_deleted', 'tag_id'],
            'require' => false,
            'nonNull' => false,
            'toNull' => ['search_value'],
        ],
    ];

    protected $rule = [
        'page' => 'number|between:1,2147483647',
        'list_rows' => 'number|between:1,100',
        'search_value' => 'max:255',
        'search_keys' => 'searchKey',
        'search_like' => 'number|between:0,2',
        'order_desc' => 'stringBool',
        'order_key' => 'in:created_at,goods,comments',
        'show_deleted' => 'number|between:0,2',
        'tag_id' => 'number',
    ];

    protected $message = [
        'page.number' => '页码格式错误',
        'page.between' => '页码范围错误',
        'list_rows.number' => '列表长度格式错误',
        'list_rows.between' => '列表长度范围错误(1-100)',
        'order_desc.stringBool' => '排序方式格式错误',
        'order_key.in' => '排序字段仅支持发布时间、喜欢数和讨论数',
    ];

    public static function requestSchemas(): array
    {
        return [
            'list' => [
                'type' => 'object',
                'properties' => [
                    'page' => ['type' => 'integer', 'minimum' => 1],
                    'list_rows' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    'search_keys' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'search_like' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2],
                    'search_value' => ['type' => 'string', 'maxLength' => 255],
                    'order_desc' => ['type' => 'boolean'],
                    'order_key' => ['type' => 'string', 'enum' => ['created_at', 'goods', 'comments']],
                    'show_deleted' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2],
                    'tag_id' => ['type' => 'integer'],
                ],
                'additionalProperties' => false,
            ],
        ];
    }
}
