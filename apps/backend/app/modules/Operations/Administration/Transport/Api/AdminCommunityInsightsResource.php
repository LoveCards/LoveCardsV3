<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

final class AdminCommunityInsightsResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'drafts' => ['type' => 'object'], 'library' => ['type' => 'object'],
            'history' => ['type' => 'object'], 'privacy' => ['type' => 'object'],
        ]];
    }
    public static function make($value): array { $data = self::normalize($value); return is_array($data) ? $data : []; }
}
