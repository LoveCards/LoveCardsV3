<?php

namespace app\modules\Platform\Rbac\Infrastructure\Persistence\Model;

use think\Model;

final class Capabilities extends Model
{
    protected $pk = 'capability';
    protected $autoWriteTimestamp = false;
    protected $schema = [
        'capability' => 'string',
        'description' => 'string',
        'risk_level' => 'int',
    ];
}
