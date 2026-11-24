<?php

namespace app\modules\Platform\Files\Infrastructure;

use app\modules\Platform\Files\Contract\StorageDriver;
use app\modules\Platform\Files\Contract\LocalStorageReader;
use app\modules\Platform\Storage\Public\Gateway;
use think\facade\Log;

/**
 * 基于 StorageFactory 的存储驱动实现
 *
 * 封装 StorageFactory 的静态调用，使 Application 层通过 Port 访问。
 */
class DefaultStorageDriver implements StorageDriver, LocalStorageReader
{
    public function uploadToDefault(object $file, string $path): array
    {
        $defaultChannel = Gateway::defaultChannel();
        $driver = Gateway::make($defaultChannel['slug']);
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
            return Gateway::make($channelSlug)->delete($driverPath);
        } catch (\Throwable $e) {
            Log::error('Storage hardDelete driver failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getUrl(string $channelSlug, string $filePath): string
    {
        $driver = Gateway::make($channelSlug);
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
        $driver = Gateway::make($channelSlug);
        if (!Gateway::supportsDirectUpload($driver)) {
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
        return Gateway::supportsDirectUpload(Gateway::make($channelSlug));
    }

    public function resolveLocalFile(string $channelSlug, string $driverPath): array
    {
        return Gateway::resolveLocalFile(Gateway::make($channelSlug), $driverPath);
    }
}
