<?php
return [
    'user_image_size' => ['type' => 'int',    'default' => 2,                        'description' => '旧全局图片大小限制(MB)，已由 avatar/content 场景键接管，仅作回落'],
    'user_image_ext'  => ['type' => 'string', 'default' => 'jpg,png,gif,webp,jpeg',  'description' => '旧全局图片扩展名限制，已由 avatar/content 场景键接管，仅作回落'],
    'avatar_size'     => ['type' => 'int',    'default' => 2,                        'description' => '头像与资料封面上传大小限制(MB)'],
    'avatar_ext'      => ['type' => 'string', 'default' => 'jpg,png,gif,webp,jpeg',  'description' => '头像与资料封面允许的扩展名'],
    'content_size'    => ['type' => 'int',    'default' => 2,                        'description' => '卡片与评论图片及直传大小限制(MB)'],
    'content_ext'     => ['type' => 'string', 'default' => 'jpg,png,gif,webp,jpeg',  'description' => '卡片与评论图片及直传允许的扩展名'],
];
