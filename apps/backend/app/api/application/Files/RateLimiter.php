<?php

namespace app\api\application\Files;

/**
 * 上传限流 Port
 *
 * 隔离 Application 层对 CacheManager 的直接依赖。
 */
interface RateLimiter
{
    /**
     * 检查是否超过限流阈值
     */
    public function checkUploadRate(string $uid): bool;
}
