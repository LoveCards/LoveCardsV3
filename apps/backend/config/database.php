<?php

$backendRoot = dirname(__DIR__);
$persistent = \app\shared\SystemPaths\PersistentConfiguration::database($backendRoot);
$pdoParams = defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')
    ? [constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY') => true]
    : [];

// MySQL 8 兼容性：mysqlnd 只按 charset=utf8mb4 握手时，连接 collation 会被钉在
// utf8mb4_general_ci，而 8.0 上以 DEFAULT CHARSET=utf8mb4 建的表实际是
// utf8mb4_0900_ai_ci；UNION/派生表列与表列对比会抛 Illegal mix of collations。
// 用 PHP_DATABASE_COLLATION 显式对齐连接 collation（MySQL 8 → utf8mb4_0900_ai_ci；
// env('database.collation') 按 ThinkPHP 约定只读 PHP_ 前缀）；
// MySQL 5.7 没有 0900 系列，留空保持默认，行为不变。
$connectionCollation = (string) env('database.collation', $persistent['collation'] ?? '');
if ($connectionCollation !== '' && preg_match('/^[A-Za-z0-9_]+$/', $connectionCollation)
    && defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
    $pdoParams[constant('PDO::MYSQL_ATTR_INIT_COMMAND')] = 'SET collation_connection = ' . $connectionCollation;
}

return [
    // 默认使用的数据库连接配置
    'default'         => env('database.driver', 'mysql'),

    // 自定义时间查询规则
    'time_query_rule' => [],

    // 自动写入时间戳字段
    // true为自动识别类型 false关闭
    // 字符串则明确指定时间字段类型 支持 int timestamp datetime date
    'auto_timestamp'  => true,

    // 时间字段取出后的默认时间格式
    'datetime_format' => 'Y-m-d H:i:s',

    // 时间字段配置 配置格式：create_time,update_time
    'datetime_field'  => '',

    // 数据库连接配置信息
    'connections'     => [
        'mysql' => [
            // 数据库类型
            'type'            => env('database.type', 'mysql'),
            // 服务器地址
            'hostname'        => env('database.hostname', $persistent['hostname'] ?? 'localhost'),
            // 数据库名
            'database'        => env('database.database', $persistent['database'] ?? 'testtest'),
            // 用户名
            'username'        => env('database.username', $persistent['username'] ?? 'testtest'),
            // 密码
            'password'        => env('database.password', $persistent['password'] ?? ''),
            // 端口
            'hostport'        => env('database.hostport', $persistent['hostport'] ?? '3306'),
            // 数据库连接参数
            'params'          => $pdoParams,
            // 数据库编码默认采用utf8
            'charset'         => env('database.charset', $persistent['charset'] ?? 'utf8mb4'),
            // 数据库表前缀
            'prefix'          => env('database.prefix', ''),

            // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
            'deploy'          => 0,
            // 数据库读写是否分离 主从式有效
            'rw_separate'     => false,
            // 读写分离后 主服务器数量
            'master_num'      => 1,
            // 指定从服务器序号
            'slave_no'        => '',
            // 是否严格检查字段是否存在
            'fields_strict'   => true,
            // 是否需要断线重连
            'break_reconnect' => false,
            // 监听SQL
            'trigger_sql'     => env('app_debug', true),
            // 开启字段缓存
            'fields_cache'    => false,
        ],

        // 更多的数据库配置信息
    ],
];
