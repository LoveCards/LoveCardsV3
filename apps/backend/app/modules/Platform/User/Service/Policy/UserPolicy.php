<?php

namespace app\modules\Platform\User\Service\Policy;

use app\modules\Platform\User\Infrastructure\Persistence\Model\Users;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\OwnedResourcePolicy;

final class UserPolicy
{
    public static function assertCan(GrantSet $grants, string $action, int $id, int $actorId, bool $withTrashed = false): void
    {
        OwnedResourcePolicy::assertOne(Users::class, 'id', $grants, 'users.' . $action, $id, $actorId, $withTrashed);
    }
    public static function assertCanBatch(GrantSet $grants, string $action, array $ids, int $actorId, bool $withTrashed = false): void
    {
        OwnedResourcePolicy::assertMany(Users::class, 'id', $grants, 'users.' . $action, $ids, $actorId, $withTrashed);
    }
}
