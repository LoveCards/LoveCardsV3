<?php

use app\ExceptionHandle;
use app\api\application\Auth\CapabilityProvider;
use app\api\application\Auth\UserRepository;
use app\api\application\Auth\VisitorPolicy;
use app\api\infrastructure\Auth\ConfigVisitorPolicy;
use app\api\infrastructure\Auth\RbacCapabilityProvider;
use app\api\infrastructure\Auth\ThinkOrmUserRepository;
use app\common\contract\TokenService;
use app\common\infra\JwtTokenService;

// 容器Provider定义文件
return [
    'think\exception\Handle' => ExceptionHandle::class,
    CapabilityProvider::class => RbacCapabilityProvider::class,
    UserRepository::class => ThinkOrmUserRepository::class,
    VisitorPolicy::class => ConfigVisitorPolicy::class,
    TokenService::class => JwtTokenService::class,
];
