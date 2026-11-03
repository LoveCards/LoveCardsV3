<?php

declare(strict_types=1);

namespace app\modules\Platform\Files\Application;

use app\modules\Platform\Files\Contract\FilePurgeRepository;
use app\modules\Platform\Files\Contract\FileReferenceRepository;
use app\modules\Platform\Files\Contract\StorageDriver;
use app\modules\Platform\Files\Domain\FileConstants;
use app\shared\Transaction\TransactionManager;

/** 执行可重试的存储物理删除，成功后再移除数据库记录。 */
final class ProcessFilePurge
{
    public function __construct(
        private FilePurgeRepository $files,
        private FileReferenceRepository $references,
        private StorageDriver $storage,
        private TransactionManager $transactions
    ) {
    }

    public function execute(int $fileId): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $file = $this->files->claim($fileId, $now);
        if ($file === null) return;

        $state = (string) ($file['purge_status'] ?? '');
        if ($state !== FileConstants::PURGE_STORAGE_DELETED) {
            try {
                $channel = trim((string) ($file['channel_slug'] ?? ''));
                $path = trim((string) ($file['driver_path'] ?? $file['file_path'] ?? ''));
                if ($channel === '' || $path === '') {
                    throw new \RuntimeException('文件存储定位信息不完整');
                }
                $deleted = $this->storage->deleteFile(
                    $channel,
                    $path
                );
                if (!$deleted) throw new \RuntimeException('存储驱动未确认文件删除成功');
                $this->files->markStorageDeleted($fileId, $now);
            } catch (\Throwable $exception) {
                $this->files->markFailed($fileId, $exception->getMessage(), $now);
                throw $exception;
            }
        }

        $this->transactions->run(function () use ($fileId): void {
            $locked = $this->files->lockForFinalize($fileId);
            if ($locked === null) return;
            if ((string) ($locked['purge_status'] ?? '') !== FileConstants::PURGE_STORAGE_DELETED) {
                throw new \RuntimeException('文件 purge 状态已变化');
            }
            if ($this->references->countForFile($fileId) > 0) {
                throw new \RuntimeException('文件在 purge 期间出现业务引用');
            }
            $this->files->deleteRecord($fileId);
        });
    }
}
