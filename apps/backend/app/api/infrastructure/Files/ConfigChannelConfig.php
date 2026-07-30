<?php

namespace app\api\infrastructure\Files;

use app\api\application\Files\ChannelConfig;
use app\api\service\Storage\ChannelManager;

/**
 * 基于 ChannelManager 的渠道配置实现
 *
 * 封装 ChannelManager 的静态调用，使 Application 层通过 Port 访问。
 */
class ConfigChannelConfig implements ChannelConfig
{
    public function getDefaultChannel(): array
    {
        return ChannelManager::getDefaultChannel();
    }

    public function getAllChannels(): array
    {
        return ChannelManager::getAll();
    }

    public function getRateLimitSettings(): array
    {
        return ChannelManager::getRateLimitSettings();
    }

    public function getDirectUploadExpire(): int
    {
        return ChannelManager::getDirectUploadExpire();
    }
}
