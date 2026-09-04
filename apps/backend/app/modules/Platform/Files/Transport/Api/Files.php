<?php

namespace app\modules\Platform\Files\Transport\Api;

use think\Validate;

class Files extends Validate
{
    public static function requestSchemas(): array
    {
        $pagination = [
            'page' => ['type' => 'integer'], 'list_rows' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
            'search_keys' => ['type' => 'array'], 'search_like' => ['type' => 'integer'], 'search_value' => ['type' => 'string'],
            'order_desc' => ['type' => 'boolean'], 'order_key' => ['type' => 'string'], 'show_deleted' => ['type' => 'integer'], 'tag_id' => ['type' => 'integer'],
            'status' => ['type' => 'integer'], 'upload_status' => ['type' => 'integer'], 'scene' => ['type' => 'string', 'maxLength' => 32],
            'ref_type' => ['type' => 'string', 'maxLength' => 32], 'ref_id' => ['type' => 'integer'],
        ];
        return [
            'list' => ['type' => 'object', 'properties' => $pagination, 'additionalProperties' => false],
            'listOwn' => ['type' => 'object', 'properties' => $pagination, 'additionalProperties' => false],
            'direct' => ['type' => 'object', 'properties' => ['filename' => ['type' => 'string', 'maxLength' => 255], 'size' => ['type' => 'integer', 'minimum' => 1], 'mime' => ['type' => 'string', 'maxLength' => 128]], 'required' => ['filename', 'size', 'mime'], 'additionalProperties' => false],
            'cleanup' => ['type' => 'object', 'properties' => [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
            ], 'additionalProperties' => false],
        ];
    }

    public static $all_scene = [
        'upload' => [
            'normal' => ['scene', 'ref_type', 'ref_id', 'is_public'],
            'require' => ['file'],
            'nonNull' => false,
            'toNull' => false,
        ],
        'default' => [
            'normal' => ['scene', 'ref_type', 'ref_id', 'is_public', 'method'],
            'require' => [],
            'nonNull' => false,
            'toNull' => false,
        ],
        'batch' => [
            'normal' => ['ids', 'method'],
            'require' => ['ids', 'method'],
            'nonNull' => false,
            'toNull' => false,
        ],
        'list' => [
            'normal' => ['page', 'list_rows', 'search_keys', 'search_like', 'search_value', 'order_desc', 'order_key', 'show_deleted', 'tag_id', 'status', 'upload_status', 'scene', 'ref_type', 'ref_id'],
            'require' => false, 'nonNull' => false, 'toNull' => false,
        ],
        'listOwn' => [
            'normal' => ['page', 'list_rows', 'search_keys', 'search_like', 'search_value', 'order_desc', 'order_key', 'show_deleted', 'tag_id', 'status', 'upload_status', 'scene', 'ref_type', 'ref_id'],
            'require' => false, 'nonNull' => false, 'toNull' => false,
        ],
        'direct' => [
            'normal' => false, 'require' => ['filename', 'size', 'mime'], 'nonNull' => false, 'toNull' => false,
        ],
        'cleanup' => [
            'normal' => ['limit'], 'require' => false, 'nonNull' => false, 'toNull' => false,
        ],
    ];

    protected $rule = [
        'ids'       => 'arrayJson',
        'file'      => 'require',
        'scene'     => 'in:card,comment,avatar,profile_cover,direct',
        'ref_type'  => 'max:32',
        'ref_id'    => 'integer',
        'is_public' => 'in:0,1',
        'method'    => 'in:approve,ban,toggle_public,trash,restore,hard_delete',
        'page' => 'number',
        'list_rows' => 'number|between:1,1000',
        'search_keys' => 'arrayJson',
        'search_like' => 'number|between:0,2',
        'search_value' => 'max:255',
        'order_desc' => 'stringBool',
        'order_key' => 'alphaDash',
        'show_deleted' => 'number|between:0,2',
        'tag_id' => 'number',
        'status' => 'number',
        'upload_status' => 'number',
        'limit' => 'number|between:1,1000',
        'filename' => 'require|max:255',
        'size' => 'require|number|between:1,10737418240',
        'mime' => 'require|max:128',
    ];

}
