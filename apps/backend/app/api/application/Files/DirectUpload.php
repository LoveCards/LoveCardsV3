<?php

namespace app\api\application\Files;

use app\api\ApiException;

/**
 * 直传文件用例
 *
 * 创建 pending 记录并返回上传凭证，确认上传完成。
 */
final class DirectUpload
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
     * 创建直传 pending 记录
     *
     * @return array 包含 record_id、upload_url、method、headers、form_data、expire
     */
    public function createPending(string $filename, string $mime, int $size, int $userId): array
    {
        if (!$this->limiter->checkUploadRate((string) $userId)) {
            throw ApiException::tooMany('请求过于频繁');
        }

        $defaultChannel = $this->channels->getDefaultChannel();
        $channelSlug = $defaultChannel['slug'];
        $path = $this->channels->generatePath($filename);

        if (!$this->driver->supportsDirectUpload($channelSlug)) {
            throw new ApiException('该渠道不支持直传');
        }

        $expire = $this->channels->getDirectUploadExpire();
        $expireAt = date('Y-m-d H:i:s', time() + $expire);

        $id = $this->files->createPendingRecord(
            bin2hex(random_bytes(8)),
            $channelSlug,
            $userId,
            $filename,
            $path,
            $size,
            $mime,
            $expireAt
        );

        $credential = $this->driver->getDirectUploadCredential(
            $channelSlug,
            $filename,
            $mime,
            $size,
            $path,
            $expire
        );

        return [
            'record_id' => $id,
            'upload_url' => $credential['url'],
            'method' => $credential['method'],
            'headers' => $credential['headers'],
            'form_data' => $credential['form_data'],
            'expire' => $credential['expire'],
        ];
    }

    /**
     * 确认上传完成
     */
    public function confirm(int $recordId, int $userId): bool
    {
        $file = $this->files->findOwnPending($recordId, $userId);
        if ($file === null) {
            return false;
        }

        if ($this->files->isExpired($recordId)) {
            $this->files->markAsFailed($recordId);
            return false;
        }

        try {
            $meta = $this->files->getChannelAndDriverPath($recordId);
            if ($meta === null) {
                $this->files->markAsFailed($recordId);
                return false;
            }

            $url = $this->driver->getUrl($meta['channel_slug'], $meta['driver_path'] ?: $file['file_path'] ?? '');
            $this->files->markAsCompleted($recordId, $url, $file['file_path'] ?? '');
            return true;
        } catch (\Exception $e) {
            $this->files->markAsFailed($recordId);
            return false;
        }
    }

    /**
     * 清理过期 pending 记录
     */
    public function cleanup(int $limit = 100): array
    {
        return $this->files->cleanupExpired($limit);
    }
}
