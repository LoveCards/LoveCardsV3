<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Transport\Api;

use think\Validate;

/**
 * Realtime Gateway 内部 ticket 请求场景（InternalTokenCheck 之后仍做字段防线）。
 */
final class InternalRealtimeValidate extends Validate
{
    public static $all_scene = [
        'verify' => [
            'normal' => [], 'require' => ['ticket'], 'nonNull' => false, 'toNull' => [],
        ],
    ];

    protected $rule = [
        'ticket' => 'require|max:64',
    ];
}
