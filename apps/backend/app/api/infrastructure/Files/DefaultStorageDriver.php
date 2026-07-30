<?php

namespace app\api\infrastructure\Files;

use think\file\UploadedFile;
use app\api\application\Files\StorageDriver;
use app\api\service\Storage\StorageFactory;
use app\api\service\Storage\Contract\StorageResult;
use app\api\service\Storage\Contract\DirectUploadCredential;
use app\api\service\Storage\Contract\HasDirectUpload;

/**
 * 基于 StorageFactory 的存储驱动实现
 *
 * 封装 StorageFactory 的静态调用，使 Application 层通过 Port 访问。
 */
class DefaultStorageDriver implements StorageDriver
{
    public function uploadToDefault(UploadedFile $file, string $path): StorageResult
    {
        $defaultChannel = \app\api\service\Storage\ChannelManager::getDefaultChannel();
        $driver = StorageFactory::make($defaultChannel['slug']);
        return $driver->upload($file, $path);
    }

    public function deleteFile(string $channelSlug, string $driverPath): bool
    {
        $driver = StorageFactory::make($channelSlug);
        return $driver->delete($driverPath);
    }

    public function getUrl(string $channelSlug, string $filePath): string
    {
        $driver = StorageFactory::make($channelSlug);
        return $driver->getUrl($filePath);
    }

    public function getDirectUploadCredential(
        string $channelSlug,
        string $filename,
        string $mime,
        int $size,
        string $path,
        int $expire
    ): DirectUploadCredential {
        $driver = StorageFactory::make($channelSlug);
        if (!$driver instanceof HasDirectUpload) {
            throw new \app\api\ApiException('该渠道不支持直传');
        }
        return $driver->getUploadCredential($filename, $mime, $size, $path, $expire);
    }

    public function supportsDirectUpload(string $channelSlug): bool
    {
        $driver = StorageFactory::make($channelSlug);
        return $driver instanceof HasDirectUpload;
    }
}
