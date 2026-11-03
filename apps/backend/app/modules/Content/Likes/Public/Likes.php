<?php

namespace app\modules\Content\Likes\Public;

use app\modules\Content\Infrastructure\Persistence\Model\Likes as LikesModel;

final class Likes
{
    public static function count(): int { return (int) LikesModel::count(); }
}
