<?php
declare(strict_types=1);

$backendRoot = dirname(__DIR__);
$resolvedInstanceRoot = getenv('LC_INSTANCE_ROOT_RESOLVED');
$defaultInstanceRoot = is_string($resolvedInstanceRoot) && trim($resolvedInstanceRoot) !== ''
    ? trim($resolvedInstanceRoot)
    : dirname($backendRoot) . DIRECTORY_SEPARATOR . '.lovecards-instance';

return [
    // 持久实例目录必须位于不可变 Release 之外；可通过环境变量挂载到独立磁盘。
    'instance_root' => env('LC_INSTANCE_ROOT', $defaultInstanceRoot),
    // JSON 对象：{"release-key-id":"/absolute/path/to/public-key.pem"}
    'trusted_release_keys' => env('LC_RELEASE_TRUSTED_KEYS_JSON', '{}'),
    'maximum_release_zip_bytes' => 536870912,
    'maximum_release_entries' => 20000,
    'maximum_release_file_bytes' => 134217728,
    'maximum_release_unpacked_bytes' => 1073741824,
    'maximum_release_manifest_bytes' => 4194304,
    'runtime' => [
        // JSON argv arrays. Empty arrays keep worker management fully disabled.
        'drain_command' => env('LC_RUNTIME_DRAIN_COMMAND_JSON', '[]'),
        'reload_command' => env('LC_RUNTIME_RELOAD_COMMAND_JSON', '[]'),
        'command_timeout_seconds' => (int) env('LC_RUNTIME_COMMAND_TIMEOUT', 30),
        // CLI upgrades may optionally probe the Web bootstrap after activation.
        'health_url' => env('LC_RUNTIME_HEALTH_URL', ''),
        'health_timeout_seconds' => (int) env('LC_RUNTIME_HEALTH_TIMEOUT', 5),
        'health_allow_remote' => env('LC_RUNTIME_HEALTH_ALLOW_REMOTE', false),
    ],
];
