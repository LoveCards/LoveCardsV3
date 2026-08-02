<?php

namespace app\modules\Content\Tags\Public;

use app\modules\Content\Tags\Service\Tags as TagsService;

final class Tags
{
    public static function list(array $params): array { return TagsService::Index($params); }
    public static function get(int $id): array { return TagsService::get($id); }
}
