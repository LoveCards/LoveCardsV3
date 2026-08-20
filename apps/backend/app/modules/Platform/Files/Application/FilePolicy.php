<?php

namespace app\modules\Platform\Files\Application;

use app\api\ApiException;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\Scope;

final class FilePolicy
{
    public static function assertOwners(GrantSet $grants, string $action, array $idOwnerMap, int $actorId): void
    {
        $capability = 'files.' . $action;
        if ($grants->allows($capability, Scope::ALL)) return;
        if (!$grants->allows($capability, Scope::OWN)) throw ApiException::forbidden('权限不足');
        foreach ($idOwnerMap as $ownerId) {
            if ((int) $ownerId !== $actorId) throw ApiException::forbidden('无权操作其他用户的文件');
        }
    }
}
