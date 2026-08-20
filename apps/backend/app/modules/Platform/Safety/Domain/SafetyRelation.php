<?php

namespace app\modules\Platform\Safety\Domain;

final class SafetyRelation
{
    public const BLOCK = 'block';
    public const MUTE = 'mute';

    public static function assertAllowed(int $actorId, int $targetId, string $kind): void
    {
        if ($actorId <= 0 || $targetId <= 0 || $actorId === $targetId) {
            throw new \InvalidArgumentException('安全关系用户无效');
        }
        if (!in_array($kind, [self::BLOCK, self::MUTE], true)) {
            throw new \InvalidArgumentException('安全关系类型无效');
        }
    }
}
