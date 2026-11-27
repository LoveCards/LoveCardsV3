<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Contract;

/** 批次和冻结收件快照的持久化边界；不暴露 Eventing 的存储模型。 */
interface SystemNoticeBatchRepository
{
    public function inSnapshot(callable $callback): mixed;
    public function find(int $id, ?int $creatorId = null, bool $lock = false): ?array;
    public function findRequest(int $creatorId, string $requestKey, bool $lock = false): ?array;
    public function latest(int $creatorId): ?array;
    public function insert(array $batch): int;
    public function update(int $id, array $changes): void;
    public function addRecipients(int $batchId, array $userIds): void;
    public function recipientsAfter(int $batchId, int $afterUserId, int $limit): array;
    public function attachDispatch(int $batchId, int $userId, int $eventId, int $deliveryId): void;
    public function lockProjection(int $eventId): ?array;
    public function completeProjection(int $eventId, int $notificationId): void;
    public function counts(int $batchId): array;
    public function pendingDeliveryIds(int $batchId): iterable;
}
