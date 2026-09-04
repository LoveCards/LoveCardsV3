<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Transport\Api;

use app\modules\Platform\Notifications\Contract\NotificationTypeCatalog;
use app\shared\Transport\Http\Resource;

final class NotificationPreferencesResource extends Resource
{
    public static function make($value): array
    {
        $data = self::normalize($value);
        $inApp = [];
        foreach (NotificationTypeCatalog::configurableTypes() as $type) $inApp[$type] = (bool) ($data['in_app'][$type] ?? true);
        return ['in_app' => $inApp, 'locked_types' => NotificationTypeCatalog::lockedTypes()];
    }

    public static function schema(string $variant = 'default'): array
    {
        $properties = [];
        foreach (NotificationTypeCatalog::configurableTypes() as $type) $properties[$type] = ['type' => 'boolean'];
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['in_app', 'locked_types'],
            'properties' => [
                'in_app' => ['type' => 'object', 'additionalProperties' => false, 'required' => array_keys($properties), 'properties' => $properties],
                'locked_types' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => NotificationTypeCatalog::lockedTypes()]],
            ],
        ];
    }
}
