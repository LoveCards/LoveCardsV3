<?php

namespace app\modules\Platform\Files\Application;

use app\modules\Platform\Files\Contract\ChannelConfig;
use app\modules\Platform\Files\Contract\FileRepository;

/**
 * 渠道统计用例
 */
final class ChannelStats
{
    private FileRepository $files;
    private ChannelConfig $channels;

    public function __construct(FileRepository $files, ChannelConfig $channels)
    {
        $this->files = $files;
        $this->channels = $channels;
    }

    /**
     * 获取各渠道文件统计
     */
    public function execute(): array
    {
        $channels = $this->channels->getAllChannels();
        $result = [];

        foreach ($channels as $config) {
            $slug = $config['slug'];
            $stats = $this->files->statsByChannel($slug);
            $result[$slug] = $stats;
        }

        return $result;
    }
}
