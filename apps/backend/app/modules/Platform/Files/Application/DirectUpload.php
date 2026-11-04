<?php

namespace app\modules\Platform\Files\Application;

use app\api\ApiException;
use app\modules\Platform\Files\Contract\ChannelConfig;
use app\modules\Platform\Files\Contract\FileRepository;
use app\modules\Platform\Files\Contract\RateLimiter;
use app\modules\Platform\Files\Contract\StorageDriver;
use app\shared\Observability\AdminMutationAudit;
use app\shared\Transaction\TransactionManager;

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

        // 直传不携带场景，按内容类限额在签发凭证之前过闸，见 UploadLimits。
        UploadLimits::enforcePending($filename, $size);

        $defaultChannel = $this->channels->getDefaultChannel();
        $channelSlug = $defaultChannel['slug'];
        $path = $this->channels->generatePath($filename);

        if (!$this->driver->supportsDirectUpload($channelSlug)) {
            throw ApiException::badRequest('该渠道不支持直传');
        }

        $expire = $this->channels->getDirectUploadExpire();
        $expireAt = date('Y-m-d H:i:s', time() + $expire);

        $hash = bin2hex(random_bytes(8));
        $id = $this->files->createPendingRecord(
            $hash,
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

            $hash = trim((string) ($meta['hash'] ?? $file['hash'] ?? ''));
            if (preg_match('/^[a-f0-9]{16,64}$/', $hash) !== 1) {
                $this->files->markAsFailed($recordId);
                return false;
            }
            $url = '/media/' . $hash;
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
    public function cleanup(
        int $limit = 100,
        ?int $actorId = null,
        string $ipAddress = ''
    ): array
    {
        if ($actorId === null) return $this->files->cleanupExpired($limit);
        return app(TransactionManager::class)->run(function () use ($limit, $actorId, $ipAddress): array {
            $cleaned = $this->files->cleanupExpired($limit);
            $before = [];
            $after = [];
            foreach ($cleaned as $item) {
                $id = (int) ($item['id'] ?? 0);
                if ($id < 1) continue;
                $before[$id] = [
                    'id' => $id,
                    'upload_status' => 0,
                    'expire_at' => $item['expired_at'] ?? null,
                ];
                $after[$id] = $this->files->findByIdWithTrashed($id);
            }
            app(AdminMutationAudit::class)->recordChanges(
                $actorId,
                'admin.file.cleanup_expired',
                'file',
                $before,
                $after,
                $ipAddress
            );
            return $cleaned;
        });
    }
}
