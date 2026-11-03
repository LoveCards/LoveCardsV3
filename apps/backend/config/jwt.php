<?php
$backendRoot = dirname(__DIR__);
$keyDefaults = \app\shared\SystemPaths\PersistentConfiguration::jwtKeyDefaults($backendRoot);

return [
    'privateKey' => env('jwt.private_key', $keyDefaults['private']),
    'publicKey' => env('jwt.public_key', $keyDefaults['public']),
    'alg' => 'RS256',
    'exp' => 3600 * 24 * 10, // token过期时间，单位秒
    'iss' => 'http://serverhub.com',
    'cacheTime' => 3600 * 24 * 3, // token 过期后的续期宽限期，单位秒
    // 旧版无 JTI Token 的绝对迁移截止时间（Unix 时间戳）；0 表示关闭迁移桥。
    'legacyBridgeUntil' => (int) env('jwt.legacy_bridge_until', 0),
];
