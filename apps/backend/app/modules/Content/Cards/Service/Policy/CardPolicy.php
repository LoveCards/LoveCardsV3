<?php

namespace app\modules\Content\Cards\Service\Policy;

use app\modules\Content\Infrastructure\Persistence\Model\Cards;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\OwnedResourcePolicy;

final class CardPolicy
{
    public static function assertCan(GrantSet $grants, string $action, int $id, int $actorId, bool $withTrashed = false): void
    {
        OwnedResourcePolicy::assertOne(Cards::class, 'user_id', $grants, 'cards.' . $action, $id, $actorId, $withTrashed);
    }
    public static function assertCanBatch(GrantSet $grants, string $action, array $ids, int $actorId, bool $withTrashed = false): void
    {
        OwnedResourcePolicy::assertMany(Cards::class, 'user_id', $grants, 'cards.' . $action, $ids, $actorId, $withTrashed);
    }
}
