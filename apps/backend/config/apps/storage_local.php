<?php

// The minimum FPM + MySQL profile always has a usable local storage channel.
// Lifecycle-managed installs resolve this relative root to shared/storage;
// legacy development installs retain public/storage compatibility.
return [
    'root' => [
        'type' => 'string',
        'default' => 'public/storage',
        'description' => '本地存储根目录',
    ],
    'url_prefix' => [
        'type' => 'string',
        'default' => '/storage',
        'description' => '本地存储 URL 前缀',
    ],
    'allow_mime_types' => [
        'type' => 'string',
        'default' => 'image/jpeg,image/png,image/gif,image/webp,application/pdf',
        'description' => '允许的 MIME 类型',
    ],
    'max_file_size' => [
        'type' => 'int',
        'default' => 10485760,
        'description' => '单文件最大字节数',
    ],
    'path_template' => [
        'type' => 'string',
        'default' => '{date}/{uuid}.{ext}',
        'description' => '对象路径模板',
    ],
];
