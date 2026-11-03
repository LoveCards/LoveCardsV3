<?php

namespace app\shared\Authorization;

use app\api\ApiException;

final class OwnedResourcePolicy
{
    public static function assertOne(
        string $modelClass,
        string $ownerField,
        GrantSet $grants,
        string $capability,
        int $resourceId,
        int $actorId,
        bool $includeTrashed = false
    ): void {
        $resource = self::query($modelClass, $includeTrashed)
            ->where('id', $resourceId)->findOrEmpty();
        if ($resource->isEmpty()) throw ApiException::notFound('资源不存在');
        if ($grants->allows($capability, Scope::ALL)) return;
        if (!$grants->allows($capability, Scope::OWN)) throw ApiException::forbidden('权限不足');
        if ((int) $resource->$ownerField !== $actorId) throw ApiException::forbidden('无权操作该资源');
    }

    public static function assertMany(
        string $modelClass,
        string $ownerField,
        GrantSet $grants,
        string $capability,
        array $resourceIds,
        int $actorId,
        bool $includeTrashed = false
    ): void {
        $ids = array_values(array_unique(array_map('intval', $resourceIds)));
        if ($ids === []) return;
        $rows = self::query($modelClass, $includeTrashed)
            ->whereIn('id', $ids)->field('id,' . $ownerField)->select()->toArray();
        if (count($rows) !== count($ids)) throw ApiException::notFound('资源不存在');
        if ($grants->allows($capability, Scope::ALL)) return;
        if (!$grants->allows($capability, Scope::OWN)) throw ApiException::forbidden('权限不足');
        foreach ($rows as $row) {
            if ((int) $row[$ownerField] !== $actorId) throw ApiException::forbidden('无权操作部分资源');
        }
    }

    private static function query(string $modelClass, bool $includeTrashed)
    {
        return $includeTrashed ? $modelClass::withTrashed() : new $modelClass();
    }
}
