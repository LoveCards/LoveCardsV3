<?php

namespace app\api\infrastructure\Files;

use app\api\application\Files\StorageDriver;
use app\api\service\Storage\StorageFactory;
use app\api\service\Storage\Contract\HasDirectUpload;
use think\facade\Log;

/**
 * 基于 StorageFactory 的存储驱动实现
 *
 * 封装 StorageFactory 的静态调用，使 Application 层通过 Port 访问。
 */
class DefaultStorageDriver implements StorageDriver
{
    public function uploadToDefault(object $file, string $path): array
    {
        $defaultChannel = \app\api\service\Storage\ChannelManager::getDefaultChannel();
        $driver = StorageFactory::make($defaultChannel['slug']);
        $result = $driver->upload($file, $path);
        return [
            'id' => $result->id,
            'url' => $result->url,
            'path' => $result->path,
            'driver_path' => $result->driverPath,
            'size' => $result->size,
            'mime_type' => $result->mimeType,
            'original_name' => $result->originalName,
            'channel_slug' => $result->channelSlug,
        ];
    }

    public function deleteFile(string $channelSlug, string $driverPath): bool
    {
        try {
            return StorageFactory::make($channelSlug)->delete($driverPath);
        } catch (\Throwable $e) {
            Log::error('Storage hardDelete driver failed: ' . $e->getMessage());
            throw $e;
        }
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
    ): array {
        $driver = StorageFactory::make($channelSlug);
        if (!$driver instanceof HasDirectUpload) {
            throw new \app\api\ApiException('该渠道不支持直传');
        }
        $credential = $driver->getUploadCredential($filename, $mime, $size, $path, $expire);
        return [
            'url' => $credential->url,
            'method' => $credential->method,
            'headers' => $credential->headers,
            'form_data' => $credential->formData,
            'expire' => $credential->expire,
        ];
    }

    public function supportsDirectUpload(string $channelSlug): bool
    {
        $driver = StorageFactory::make($channelSlug);
        return $driver instanceof HasDirectUpload;
    }
}
