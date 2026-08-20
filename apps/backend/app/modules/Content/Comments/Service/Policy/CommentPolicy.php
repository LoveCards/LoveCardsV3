<?php

namespace app\modules\Content\Comments\Service\Policy;

use app\modules\Content\Infrastructure\Persistence\Model\Comments;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\OwnedResourcePolicy;

final class CommentPolicy
{
    public static function assertCan(GrantSet $grants, string $action, int $id, int $actorId, bool $withTrashed = false): void
    {
        OwnedResourcePolicy::assertOne(Comments::class, 'user_id', $grants, 'comments.' . $action, $id, $actorId, $withTrashed);
    }
    public static function assertCanBatch(GrantSet $grants, string $action, array $ids, int $actorId, bool $withTrashed = false): void
    {
        OwnedResourcePolicy::assertMany(Comments::class, 'user_id', $grants, 'comments.' . $action, $ids, $actorId, $withTrashed);
    }
}
