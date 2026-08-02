<?php

namespace app\modules\Platform\User\Public;

use app\modules\Platform\User\Service\Users as UsersService;

final class Users
{
    public static function get(int $id): array { return UsersService::Get($id)->toArray(); }
}
