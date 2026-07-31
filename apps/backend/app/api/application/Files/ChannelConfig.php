<?php

namespace app\api\application\Files;

/**
 * 存储渠道配置 Port
 *
 * 隔离 Application 层对 ChannelManager 静态调用和 configs 表直接查询的依赖。
 */
interface ChannelConfig
{
    /**
     * 获取默认渠道配置
     */
    public function getDefaultChannel(): array;

    /**
     * 获取所有渠道配置
     */
    public function getAllChannels(): array;

    /**
     * 获取限流设置
     */
    public function getRateLimitSettings(): array;

    /**
     * 获取直传过期时间（秒）
     */
    public function getDirectUploadExpire(): int;

    /**
     * 按默认渠道生成驱动路径
     */
    public function generatePath(string $filename): string;
}
