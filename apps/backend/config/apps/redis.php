<?php
declare(strict_types=1);

/** Redis 是可选系统设施；安装后的默认状态为关闭。 */
return [
    'enabled' => ['type' => 'bool', 'default' => false, 'description' => '启用 Redis 系统连接'],
    'host' => ['type' => 'string', 'default' => '', 'description' => 'Redis 地址'],
    'port' => ['type' => 'int', 'default' => 6379, 'description' => 'Redis 端口'],
    'database' => ['type' => 'int', 'default' => 0, 'description' => 'Redis 数据库编号'],
    'tls' => ['type' => 'bool', 'default' => false, 'description' => '启用 Redis TLS 并验证证书'],
    'tls_server_name' => ['type' => 'string', 'default' => '', 'description' => 'Redis TLS 证书名称；留空使用主机地址'],
    'password' => ['type' => 'password', 'default' => '', 'description' => 'Redis 认证密码', 'sensitive' => true],
];
