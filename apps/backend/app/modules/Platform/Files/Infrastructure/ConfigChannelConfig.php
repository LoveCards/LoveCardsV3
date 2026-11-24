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
        return Gateway::defaultChannel();
    }

    public function getAllChannels(): array
    {
        return Gateway::allChannels();
    }

    public function getRateLimitSettings(): array
    {
        return Gateway::rateLimitSettings();
    }

    public function getDirectUploadExpire(): int
    {
        return Gateway::directUploadExpire();
    }

    public function generatePath(string $filename): string
    {
        return Gateway::generatePath($filename);
    }
}
