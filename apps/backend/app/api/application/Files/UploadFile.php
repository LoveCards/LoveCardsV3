<?php

namespace app\api\application\Files;

use think\file\UploadedFile;
use app\api\ApiException;

/**
 * 上传文件用例
 *
 * Controller 调用此用例，传入 uid 和文件对象。
 * 用例编排限流检查、路径生成、驱动上传和记录创建。
 */
final class UploadFile
{
    private FileRepository $files;
    private StorageDriver $driver;
    private ChannelConfig $channels;
    private RateLimiter $limiter;

    public function __construct(
        FileRepository $files,
        StorageDriver $driver,
        ChannelConfig $channels,
        RateLimiter $limiter
    ) {
        $this->files = $files;
        $this->driver = $driver;
        $this->channels = $channels;
        $this->limiter = $limiter;
    }

    /**
     * @return array 上传结果（兼容 StorageResult::toArray 格式）
     */
    public function execute(UploadedFile $file, int $userId, string $scene, ?string $refType, ?int $refId, int $isPublic): array
    {
        if (!$this->limiter->checkUploadRate((string) $userId)) {
            throw ApiException::tooMany('请求过于频繁');
        }

        $defaultChannel = $this->channels->getDefaultChannel();
        $path = \app\api\service\Storage\PathGenerator::generate($defaultChannel, $file->getOriginalName());

        $result = $this->driver->uploadToDefault($file, $path);

        $id = $this->files->create([
            'hash' => bin2hex(random_bytes(8)),
            'channel_slug' => $defaultChannel['slug'],
            'user_id' => $userId,
            'is_public' => $isPublic,
            'scene' => $scene,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'original_name' => $result->originalName,
            'file_path' => $result->path,
            'file_url' => $result->url,
            'file_size' => $result->size,
            'file_ext' => strtolower(pathinfo($result->originalName, PATHINFO_EXTENSION)),
            'mime_type' => $result->mimeType,
            'driver_path' => $result->driverPath,
            'status' => FileConstants::STATUS_NORMAL,
            'upload_status' => FileConstants::UPLOAD_COMPLETED,
        ]);

        $result->id = $id;
        return $result->toArray();
    }
}
