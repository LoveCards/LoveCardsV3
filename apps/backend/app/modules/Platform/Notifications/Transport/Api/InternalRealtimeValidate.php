<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Transport\Api;

use think\Validate;

/**
 * Realtime Gateway 内部回源接口的请求场景（InternalTokenCheck 之后仍做字段防线）。
 */
final class InternalRealtimeValidate extends Validate
{
    public static $all_scene = [
        'verify' => [
            'normal' => [], 'require' => ['ticket'], 'nonNull' => false, 'toNull' => [],
        ],
        'since' => [
            'normal' => ['cursor', 'limit'], 'require' => ['uid'], 'nonNull' => false, 'toNull' => ['cursor'],
        ],
    ];

    protected $rule = [
        'ticket' => 'require|max:64',
        'uid' => 'require|number|gt:0',
        'cursor' => 'max:191',
        'limit' => 'number|between:1,100',
    ];
}
