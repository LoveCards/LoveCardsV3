<?php

namespace app\api\application\Files;

/**
 * 存储驱动 Port
 *
 * 封装 StorageFactory 的驱动创建和操作，
 * 使 Application use case 不直接依赖 StorageFactory 静态调用。
 */
interface StorageDriver
{
    /**
     * 上传文件到存储后端
     */
    public function uploadToDefault(object $file, string $path): array;

    /**
     * 删除存储后端文件
     */
    public function deleteFile(string $channelSlug, string $driverPath): bool;

    /**
     * 获取文件 URL
     */
    public function getUrl(string $channelSlug, string $filePath): string;

    /**
     * 创建直传凭证
     */
    public function getDirectUploadCredential(
        string $channelSlug,
        string $filename,
        string $mime,
        int $size,
        string $path,
        int $expire
    ): array;

    /**
     * 检查渠道是否支持直传
     */
    public function supportsDirectUpload(string $channelSlug): bool;
}
