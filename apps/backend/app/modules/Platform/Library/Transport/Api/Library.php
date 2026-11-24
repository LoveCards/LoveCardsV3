<?php

namespace app\modules\Platform\Library\Transport\Api;

use think\Validate;

/** 校验单资源操作及批量收藏状态查询。 */
final class Library extends Validate
{
    private const MAX_CARD_IDS = 100;

    public static function requestSchemas(): array
    {
        return [
            'cardIds' => [
                'type' => 'object',
                'properties' => [
                    'card_ids' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => self::MAX_CARD_IDS,
                        'items' => ['type' => 'integer', 'minimum' => 1],
                    ],
                ],
                'required' => ['card_ids'],
                'additionalProperties' => false,
            ],
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
        'cardIds' => [
            'normal' => ['card_ids'],
            'require' => ['card_ids'],
            'nonNull' => false,
            'toNull' => false,
        ],
        'resource' => [
            'normal' => ['resource_type', 'resource_id'],
            'require' => ['resource_type', 'resource_id'],
            'nonNull' => false,
            'toNull' => false,
        ],
    ];

    protected $rule = [
        'card_ids' => 'require|cardIds',
        'resource_type' => 'require|regex:/^[a-z][a-z0-9_]{0,31}$/',
        'resource_id' => 'require|max:128|regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/',
    ];

    protected function cardIds($value): bool
    {
        if (is_string($value)) {
            $value = json_decode($value);
        }
        if (!is_array($value) || !array_is_list($value) || $value === [] || count($value) > self::MAX_CARD_IDS) {
            return false;
        }
        foreach ($value as $id) {
            if (!is_int($id) || $id <= 0) {
                return false;
            }
        }
        return true;
    }
}
