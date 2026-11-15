<?php

namespace app\modules\Content\Infrastructure\Persistence\Model;

use think\Model;

class Likes extends Model
{
    protected $table = 'likes';

    protected $autoWriteTimestamp = 'timestamp';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id' => 'int',
        'ref_type' => 'string', // 内容类型：card 或 comment
        'ref_id' => 'int',      // 内容 ID
        'uid' => 'int',
        'ip' => 'varchar',
        'created_at' => 'timestamp',
    ];
}
