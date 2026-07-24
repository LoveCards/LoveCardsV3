<?php

use app\ExceptionHandle;
use app\api\application\Auth\UserRepository;
use app\api\infrastructure\Auth\ThinkOrmUserRepository;
use app\common\contract\TokenService;
use app\common\infra\JwtTokenService;

// 容器Provider定义文件
return [
    'think\exception\Handle' => ExceptionHandle::class,
    UserRepository::class => ThinkOrmUserRepository::class,
    TokenService::class => JwtTokenService::class,
];
