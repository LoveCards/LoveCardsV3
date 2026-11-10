<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 事件消费策略快照；draining 不代表切换完成。 */
final class AdminEventConsumptionResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['mode', 'state', 'revision', 'scheduled_trigger'], 'properties' => [
            'mode' => ['type' => 'string', 'enum' => ['scheduled', 'resident']],
            'state' => ['type' => 'string', 'enum' => ['active', 'draining']],
            'target_mode' => ['type' => ['string', 'null']],
            'scheduled_trigger' => ['type' => 'string', 'enum' => ['none', 'cli', 'http']],
            'revision' => ['type' => 'integer'],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        return is_array($data) ? array_intersect_key($data, array_flip(['mode', 'state', 'target_mode', 'scheduled_trigger', 'revision'])) : [];
    }
}
