<?php

use app\ExceptionHandle;
use app\api\service\Auth\CapabilityProvider;
use app\api\service\Auth\UserRepository;
use app\api\service\Auth\VisitorPolicy;
use app\api\service\Files\Contract\FileRepository;
use app\api\service\Files\Contract\StorageDriver;
use app\api\service\Files\Contract\ChannelConfig;
use app\api\service\Files\Contract\RateLimiter;
use app\api\infrastructure\Auth\ConfigVisitorPolicy;
use app\api\infrastructure\Auth\RbacCapabilityProvider;
use app\api\infrastructure\Auth\ThinkOrmUserRepository;
use app\api\infrastructure\Files\ThinkOrmFileRepository;
use app\api\infrastructure\Files\DefaultStorageDriver;
use app\api\infrastructure\Files\ConfigChannelConfig;
use app\api\infrastructure\Files\CacheRateLimiter;
use app\common\contract\TokenService;
use app\common\infra\JwtTokenService;

// 容器Provider定义文件
return [
    'think\exception\Handle' => ExceptionHandle::class,
    CapabilityProvider::class => RbacCapabilityProvider::class,
    UserRepository::class => ThinkOrmUserRepository::class,
    VisitorPolicy::class => ConfigVisitorPolicy::class,
    TokenService::class => JwtTokenService::class,
    FileRepository::class => ThinkOrmFileRepository::class,
    StorageDriver::class => DefaultStorageDriver::class,
    ChannelConfig::class => ConfigChannelConfig::class,
    RateLimiter::class => CacheRateLimiter::class,
];
