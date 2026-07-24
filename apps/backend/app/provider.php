<?php

use app\ExceptionHandle;
use app\common\contract\TokenService;
use app\common\infra\JwtTokenService;

// 容器Provider定义文件
return [
    'think\exception\Handle' => ExceptionHandle::class,
    TokenService::class => JwtTokenService::class,
];
