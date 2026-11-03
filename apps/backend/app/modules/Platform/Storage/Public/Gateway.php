<?php

namespace app\modules\Platform\Storage\Public;

use app\modules\Platform\Storage\Infrastructure\StorageFactory;
use app\modules\Platform\Storage\Infrastructure\ChannelManager;
use app\modules\Platform\Storage\Infrastructure\PathGenerator;
use app\modules\Platform\Storage\Contract\LocalFileProvider;

/** Public storage capability used by the Files module adapter. */
final class Gateway
{
    public static function make(string $slug): object { return StorageFactory::make($slug); }
    public static function supportsDirectUpload(object $driver): bool { return $driver instanceof \app\modules\Platform\Storage\Contract\HasDirectUpload; }
    public static function defaultChannel(): array { return ChannelManager::getDefaultChannel(); }
    public static function allChannels(): array { return ChannelManager::getAll(); }
    public static function rateLimitSettings(): array { return ChannelManager::getRateLimitSettings(); }
    public static function directUploadExpire(): int { return ChannelManager::getDirectUploadExpire(); }
    public static function generatePath(string $filename): string { return PathGenerator::generate(self::defaultChannel(), $filename); }
    /** @return array{managed:bool,path:?string} */
    public static function resolveLocalFile(object $driver, string $driverPath): array
    {
        if (!$driver instanceof LocalFileProvider) return ['managed' => false, 'path' => null];
        return ['managed' => true, 'path' => $driver->resolveLocalFile($driverPath)];
    }
}
