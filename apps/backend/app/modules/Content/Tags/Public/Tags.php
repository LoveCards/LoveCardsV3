<?php

namespace app\modules\Content\Tags\Public;

use app\modules\Content\Tags\Service\Tags as TagsService;
use app\shared\Authorization\GrantSet;

final class Tags
{
    public static function list(array $params): array { return TagsService::Index($params); }
    public static function get(int $id): array { return TagsService::get($id); }
    public static function ensureTag(string $rawName, int $uid, ?GrantSet $grants = null): int
    {
        return TagsService::ensureTag($rawName, $uid, $grants);
    }
}
