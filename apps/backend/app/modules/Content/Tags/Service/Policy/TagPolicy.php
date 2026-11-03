<?php

namespace app\modules\Content\Tags\Service\Policy;

use app\modules\Content\Infrastructure\Persistence\Model\Tags;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\OwnedResourcePolicy;

final class TagPolicy
{
    public static function assertCan(GrantSet $grants, string $action, int $id, int $actorId, bool $withTrashed = false): void
    {
        OwnedResourcePolicy::assertOne(Tags::class, 'user_id', $grants, 'tags.' . $action, $id, $actorId, $withTrashed);
    }
    public static function assertCanBatch(GrantSet $grants, string $action, array $ids, int $actorId, bool $withTrashed = false): void
    {
        OwnedResourcePolicy::assertMany(Tags::class, 'user_id', $grants, 'tags.' . $action, $ids, $actorId, $withTrashed);
    }
}
