<?php
declare(strict_types=1);

namespace app\modules\Content\Cards\Transport\Api;

use think\Validate;

/** 站内公开搜索只接受关键词、排序和有界分页。 */
final class PublicCardSearchQuery extends Validate
{
    public static $all_scene = [
        'search' => [
            'normal' => ['page', 'list_rows', 'sort'],
            'require' => ['search_value'],
            'nonNull' => false,
            'toNull' => false,
        ],
    ];

    protected $rule = [
        'search_value' => 'require|validTerm|max:100',
        'page' => 'positiveInteger|between:1,2147483647',
        'list_rows' => 'positiveInteger|between:1,30',
        'sort' => 'in:match,latest',
    ];

    protected $message = [
        'search_value.max' => '搜索词不能超过 100 个字符',
        'page' => '页码格式错误',
        'list_rows' => '返回数量范围必须为 1-30',
        'sort' => '排序方式仅支持匹配程度和最新发布',
    ];

    protected function validTerm($value): bool|string
    {
        if (!is_string($value)) return '请输入搜索关键词';
        $term = trim($value);
        if (str_starts_with($term, '#')) {
            return trim(substr($term, 1)) !== '' ? true : '请输入话题名称';
        }
        return mb_strlen($term) >= 2 ? true : '输入至少两个字搜索正文';
    }

    protected function positiveInteger($value): bool
    {
        return (is_int($value) || is_string($value)) && preg_match('/^[1-9][0-9]*$/D', (string) $value) === 1;
    }

    public static function requestSchemas(): array
    {
        return ['search' => [
            'type' => 'object',
            'properties' => [
                'search_value' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 100],
                'sort' => ['type' => 'string', 'enum' => ['match', 'latest']],
                'page' => ['type' => 'integer', 'minimum' => 1],
                'list_rows' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
            ],
            'required' => ['search_value'],
            'additionalProperties' => false,
        ]];
    }
}
