<?php

namespace app\modules\Platform\Rbac\Infrastructure\Persistence\Model;

use think\Model;

final class RoleGrants extends Model
{
    protected $autoWriteTimestamp = false;
    protected $schema = [
        'role_id' => 'int',
        'capability' => 'string',
        'scope' => 'string',
        'created_at' => 'datetime',
    ];
}
