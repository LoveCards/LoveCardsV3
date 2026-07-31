<?php

namespace app\api\application\Files;

use app\api\ApiException;

/**
 * 批量操作文件用例
 *
 * 包含 approve、ban、toggle_public、trash、restore、hard_delete 操作。
 * 权限和归属检查在用例内完成，Controller 只传入 uid 和 capabilities。
 */
final class BatchOperateFiles
{
    private FileRepository $files;
    private StorageDriver $driver;

    public function __construct(FileRepository $files, StorageDriver $driver)
    {
        $this->files = $files;
        $this->driver = $driver;
    }

    /**
     * 执行批量操作
     *
     * @param string $method 操作方法名
     * @param array  $ids    文件 ID 列表
     * @param int    $userId 当前用户 ID
     * @param array  $caps   当前用户 capability 列表
     */
    public function execute(string $method, array $ids, int $userId, array $caps): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, fn($id) => $id > 0);
        if (empty($ids)) return;

        // 映射操作到基础 capability
        $methodMap = [
            'approve' => 'files.update',
            'ban' => 'files.update',
            'toggle_public' => 'files.update',
            'trash' => 'files.delete',
            'restore' => 'files.delete',
            'hard_delete' => 'files.delete',
        ];

        $baseCap = $methodMap[$method] ?? null;
        if ($baseCap === null) {
            throw ApiException::badRequest('不支持的操作');
        }

        $canAll = in_array($baseCap . '.all', $caps, true);
        $canOwn = in_array($baseCap, $caps, true);
        if (!$canAll && !$canOwn) {
            throw ApiException::forbidden('权限不足');
        }

        // 查询所有 ID（含软删除）用于存在性和归属检查
        $idOwnerMap = $this->files->findIdsWithOwner($ids);
        $foundIds = array_keys($idOwnerMap);

        // 存在性检查
        $missingIds = array_diff($ids, $foundIds);
        if (!empty($missingIds)) {
            throw ApiException::notFound('文件不存在: ' . implode(', ', $missingIds));
        }

        // 归属检查（无 .all 时必须检查）
        if (!$canAll) {
            foreach ($idOwnerMap as $id => $ownerId) {
                if ((int) $ownerId !== $userId) {
                    throw ApiException::forbidden('无权操作其他用户的文件');
                }
            }
        }

        // 执行操作
        switch ($method) {
            case 'approve':
                $this->files->batchUpdateStatus($ids, 'status', FileConstants::STATUS_NORMAL);
                break;
            case 'ban':
                $this->files->batchUpdateStatus($ids, 'status', FileConstants::STATUS_BANNED);
                break;
            case 'toggle_public':
                $this->files->batchTogglePublic($ids);
                break;
            case 'trash':
                foreach ($ids as $id) {
                    $this->files->softDelete($id);
                }
                break;
            case 'restore':
                foreach ($ids as $id) {
                    $this->files->restore($id);
                }
                break;
            case 'hard_delete':
                foreach ($ids as $id) {
                    $this->hardDelete($id);
                }
                break;
            default:
                throw ApiException::badRequest('不支持的操作');
        }
    }

    private function hardDelete(int $fileId): void
    {
        $meta = $this->files->getChannelAndDriverPath($fileId);
        if ($meta === null) return;

        $this->driver->deleteFile($meta['channel_slug'], $meta['driver_path']);

        $this->files->hardDelete($fileId);
    }
}
