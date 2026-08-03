<?php

namespace app\modules\Platform\Files\Transport\Api;

use think\Validate;

class Files extends Validate
{
    public static $all_scene = [
        'upload' => [
            'normal' => ['scene', 'ref_type', 'ref_id', 'is_public'],
            'require' => ['scene'],
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
    ];

    protected $rule = [
        'ids'       => 'arrayJson',
        'scene'     => 'in:card,comment,avatar,direct',
        'ref_type'  => 'max:32',
        'ref_id'    => 'integer',
        'is_public' => 'in:0,1',
        'method'    => 'in:approve,ban,toggle_public,trash,restore,hard_delete',
    ];
}
