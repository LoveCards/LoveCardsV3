<?php

use app\ExceptionHandle;
use app\modules\Platform\Auth\Contract\CapabilityProvider;
use app\modules\Platform\Auth\Contract\UserRepository;
use app\modules\Platform\Auth\Public\VisitorPolicy;
use app\modules\Platform\Files\Contract\FileRepository;
use app\modules\Platform\Files\Contract\StorageDriver;
use app\modules\Platform\Files\Contract\ChannelConfig;
use app\modules\Platform\Files\Contract\RateLimiter;
use app\modules\Platform\Auth\Infrastructure\ConfigVisitorPolicy;
use app\modules\Platform\Auth\Infrastructure\RbacCapabilityProvider;
use app\modules\Platform\Auth\Infrastructure\ThinkOrmUserRepository;
use app\modules\Platform\Files\Infrastructure\ThinkOrmFileRepository;
use app\modules\Platform\Files\Infrastructure\DefaultStorageDriver;
use app\modules\Platform\Files\Infrastructure\ConfigChannelConfig;
use app\modules\Platform\Files\Infrastructure\CacheRateLimiter;
use app\modules\Platform\Auth\Contract\TokenService;
use app\modules\Platform\Auth\Infrastructure\JwtTokenService;

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
