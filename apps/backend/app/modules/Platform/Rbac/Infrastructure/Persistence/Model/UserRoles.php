<?php

namespace app\modules\Platform\Rbac\Infrastructure\Persistence\Model;

use think\Model;

final class UserRoles extends Model
{
    protected $autoWriteTimestamp = false;
    protected $schema = [
        'user_id' => 'int',
        'role_id' => 'int',
        'created_at' => 'datetime',
    ];
}
