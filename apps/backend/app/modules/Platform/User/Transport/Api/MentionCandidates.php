<?php

declare(strict_types=1);

namespace app\modules\Platform\User\Transport\Api;

use think\Validate;

/** @ 提及候选用户查询参数。 */
final class MentionCandidates extends Validate
{
    public static function requestSchemas(): array
    {
        return [
            'search' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'minLength' => 1,
                        'maxLength' => 12,
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 20,
                    ],
                ],
                'required' => ['query'],
                'additionalProperties' => false,
            ],
        ];
    }

    public static $all_scene = [
        'search' => [
            'normal' => ['limit'],
            'require' => ['query'],
            'nonNull' => false,
            'toNull' => false,
        ],
    ];

    protected $rule = [
        // 用户名与 MentionParser 保持同一套字符边界。
        'query' => 'length:1,12|chsDash',
        'limit' => 'number|between:1,20',
    ];

    protected $message = [
        'query.require' => '搜索内容不能为空',
        'query.length' => '搜索内容长度必须为 1-12 个字符',
        'query.chsDash' => '搜索内容只能包含汉字、字母、数字、下划线及破折号',
        'limit.number' => '返回数量格式错误',
        'limit.between' => '返回数量范围必须为 1-20',
    ];
}
