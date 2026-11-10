<?php
declare(strict_types=1);

/** Redis 是可选系统设施；安装后的默认状态为关闭。 */
return [
    'enabled' => ['type' => 'bool', 'default' => false, 'description' => '启用 Redis 系统连接'],
    'host' => ['type' => 'string', 'default' => '127.0.0.1', 'description' => 'Redis 地址'],
    'port' => ['type' => 'int', 'default' => 6379, 'description' => 'Redis 端口'],
    'database' => ['type' => 'int', 'default' => 0, 'description' => 'Redis 数据库编号'],
    'password' => ['type' => 'password', 'default' => '', 'description' => 'Redis 认证密码', 'sensitive' => true],
];
