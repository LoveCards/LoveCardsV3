<?php

namespace app\modules\Content\Tags\Transport\Api;

use think\Validate;

/** 公开话题搜索只接受关键词和有界分页参数。 */
final class PublicTagSearchQuery extends Validate
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
        'search_value' => 'nonBlank|max:255',
        'page' => 'number|between:1,2147483647',
        'list_rows' => 'number|between:1,50',
    ];

    protected $message = [
        'search_value.max' => '搜索词不能超过 255 个字符',
        'page.number' => '页码格式错误',
        'page.between' => '页码范围错误',
        'list_rows.number' => '列表长度格式错误',
        'list_rows.between' => '列表长度范围错误(1-50)',
    ];

    protected function nonBlank($value): bool|string
    {
        return is_string($value) && trim(ltrim(trim($value), '#')) !== ''
            ? true
            : '请输入搜索关键词';
    }

    public static function requestSchemas(): array
    {
        return [
            'search' => [
                'type' => 'object',
                'properties' => [
                    'search_value' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                    'page' => ['type' => 'integer', 'minimum' => 1],
                    'list_rows' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                ],
                'required' => ['search_value'],
                'additionalProperties' => false,
            ],
        ];
    }
}
