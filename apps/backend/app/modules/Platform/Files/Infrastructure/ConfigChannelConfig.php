<?php

namespace app\modules\Platform\Files\Infrastructure;

use app\modules\Platform\Files\Contract\ChannelConfig;
use app\modules\Platform\Storage\Public\Gateway;

/**
 * 基于 ChannelManager 的渠道配置实现
 *
 * 封装 ChannelManager 的静态调用，使 Application 层通过 Port 访问。
 */
class ConfigChannelConfig implements ChannelConfig
{
    public function getDefaultChannel(): array
    {
        return class_exists(Gateway::class) ? Gateway::defaultChannel() : \app\modules\Platform\Storage\Infrastructure\ChannelManager::getDefaultChannel();
    }

    public function getAllChannels(): array
    {
        return class_exists(Gateway::class) ? Gateway::allChannels() : \app\modules\Platform\Storage\Infrastructure\ChannelManager::getAll();
    }

    public function getRateLimitSettings(): array
    {
        return class_exists(Gateway::class) ? Gateway::rateLimitSettings() : \app\modules\Platform\Storage\Infrastructure\ChannelManager::getRateLimitSettings();
    }

    public function getDirectUploadExpire(): int
    {
        return class_exists(Gateway::class) ? Gateway::directUploadExpire() : \app\modules\Platform\Storage\Infrastructure\ChannelManager::getDirectUploadExpire();
    }

    public function generatePath(string $filename): string
    {
        return class_exists(Gateway::class) ? Gateway::generatePath($filename) : \app\modules\Platform\Storage\Infrastructure\PathGenerator::generate($this->getDefaultChannel(), $filename);
    }
}
