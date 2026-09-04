<?php

namespace app\modules\Platform\User\Transport\Api;

use app\shared\Transport\Http\PublicResource;

final class ProfileResource extends PublicResource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'x-typescript-type' => 'CurrentUser',
            'additionalProperties' => true,
        ];
    }
}
