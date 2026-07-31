<?php

namespace app\api\application\Files;

/**
 * 文件持久化 Port
 *
 * 隔离 Application 层对 Files Model 和 Db facade 的直接依赖。
 * 实现类放在 infrastructure/Files/，在 provider.php 绑定。
 */
interface FileRepository
{
    /**
     * 创建文件记录并返回 ID
     */
    public function create(array $attributes): int;

    /**
     * 按 ID 查找文件（含软删除），返回原始数组或 null
     */
    public function findByIdWithTrashed(int $id): ?array;

    /**
     * 按 ID 查找文件（不含软删除），返回原始数组或 null
     */
    public function findById(int $id): ?array;

    /**
     * 按 ID 查找文件（含软删除），应用可见范围控制
     */
    public function findByIdVisible(int $id, int $userId, bool $canReadAll): ?array;

    /**
     * 按 hash 查找文件（含软删除），应用可见范围
     */
    public function findByHash(string $hash, int $userId, bool $canReadAll): ?array;

    /**
     * 按 hash 列表查找文件（含软删除），应用可见范围
     */
    public function findByHashes(array $hashes, int $userId, bool $canReadAll): array;

    /**
     * 按 ID 和 user_id 查找文件（确认上传用）
     */
    public function findOwnPending(int $recordId, int $userId): ?array;

    /**
     * 标记文件上传完成
     */
    public function markAsCompleted(int $id, string $url, string $driverPath): bool;

    /**
     * 标记文件上传失败
     */
    public function markAsFailed(int $id): bool;

    /**
     * 检查文件是否过期
     */
    public function isExpired(int $id): bool;

    /**
     * 批量查询文件（含软删除），返回 id => user_id 映射
     */
    public function findIdsWithOwner(array $ids): array;

    /**
     * 批量更新状态字段
     */
    public function batchUpdateStatus(array $ids, string $field, $value): void;

    /**
     * 批量切换公开状态
     */
    public function batchTogglePublic(array $ids): void;

    /**
     * 软删除文件
     */
    public function softDelete(int $id): void;

    /**
     * 恢复软删除文件
     */
    public function restore(int $id): void;

    /**
     * 物理删除文件记录
     */
    public function hardDelete(int $id): void;

    /**
     * 清理过期 pending 记录
     */
    public function cleanupExpired(int $limit): array;

    /**
     * 按渠道统计文件数和总大小
     */
    public function statsByChannel(string $slug): array;

    /**
     * 分页查询文件列表
     */
    public function paginate(array $params, int $userId, bool $canReadAll): array;

    /**
     * 分页查询用户自己的文件列表
     */
    public function paginateOwn(array $params, int $userId): array;

    /**
     * 获取文件的 channel_slug 和 driver_path
     */
    public function getChannelAndDriverPath(int $id): ?array;

    /**
     * 创建 pending 记录并返回 ID
     */
    public function createPendingRecord(
        string $hash,
        string $channelSlug,
        ?int $userId,
        string $filename,
        string $path,
        int $size,
        string $mime,
        string $expireAt
    ): int;
}
