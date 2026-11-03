<?php

namespace app\modules\Platform\Rbac\Public;

use app\modules\Platform\Rbac\Domain\SystemRolePolicy;
use app\shared\Authorization\GrantSet;

/** 跨模块复用的管理端垂直授权边界。 */
final class AdministrationBoundary
{
    public static function userMutationViolation(
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        int $targetUserId,
        array $targetRoleIds,
        ?array $proposedRoleIds = null,
        ?GrantSet $proposedGrants = null,
        bool $enforceTargetGrantCeiling = true
    ): ?string {
        return SystemRolePolicy::userMutationViolation(
            $actorId,
            $actorRoleIds,
            $actorGrants,
            $targetUserId,
            $targetRoleIds,
            $enforceTargetGrantCeiling ? Authorization::freshGrants($targetRoleIds) : new GrantSet(),
            $proposedRoleIds,
            $proposedGrants,
            config('system.system_roles'),
            $enforceTargetGrantCeiling
        );
    }
}
