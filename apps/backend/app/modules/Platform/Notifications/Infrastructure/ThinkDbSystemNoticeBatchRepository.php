<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Platform\Notifications\Contract\SystemNoticeBatchRepository;
use think\facade\Db;

final class ThinkDbSystemNoticeBatchRepository implements SystemNoticeBatchRepository
{
    public function inSnapshot(callable $callback): mixed
    {
        if (Db::connect()->getPdo()->inTransaction()) throw new \LogicException('批次快照必须在独立事务中读取');
        // 只影响下一次事务，不改变连接或实例的默认隔离级别。
        Db::execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        return Db::transaction($callback);
    }

    public function find(int $id, ?int $creatorId = null, bool $lock = false): ?array
    {
        $query = Db::table('system_notice_batches')->where('id', $id);
        if ($creatorId !== null) $query->where('creator_id', $creatorId);
        if ($lock) $query->lock(true);
        $row = $query->find();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findRequest(int $creatorId, string $requestKey, bool $lock = false): ?array
    {
        $query = Db::table('system_notice_batches')->where(['creator_id' => $creatorId, 'request_key' => $requestKey]);
        if ($lock) $query->lock(true);
        $row = $query->find();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function latest(int $creatorId): ?array
    {
        $row = Db::table('system_notice_batches')->where('creator_id', $creatorId)->order('id', 'desc')->find();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function insert(array $batch): int
    {
        $batch['selected_recipients'] = json_encode($batch['selected_recipients'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return (int) Db::table('system_notice_batches')->insertGetId($batch);
    }

    public function update(int $id, array $changes): void
    {
        $changes['updated_at'] = date('Y-m-d H:i:s');
        Db::table('system_notice_batches')->where('id', $id)->update($changes);
    }

    public function addRecipients(int $batchId, array $userIds): void
    {
        foreach (array_chunk($userIds, 200) as $chunk) {
            Db::table('system_notice_recipients')->insertAll(array_map(
                static fn(int $userId): array => ['batch_id' => $batchId, 'user_id' => $userId], $chunk
            ));
        }
    }

    public function recipientsAfter(int $batchId, int $afterUserId, int $limit): array
    {
        return Db::table('system_notice_recipients')->where('batch_id', $batchId)
            ->where('user_id', '>', $afterUserId)->order('user_id')->limit($limit)->select()->toArray();
    }

    public function attachDispatch(int $batchId, int $userId, int $eventId, int $deliveryId): void
    {
        $changed = Db::table('system_notice_recipients')->where(['batch_id' => $batchId, 'user_id' => $userId])
            ->whereNull('issued_event_id')->update(['issued_event_id' => $eventId, 'delivery_id' => $deliveryId]);
        if ($changed !== 1) throw new \RuntimeException('收件任务已登记，不能重复签发');
    }

    public function lockProjection(int $eventId): ?array
    {
        $batchId = Db::table('system_notice_recipients')->where('issued_event_id', $eventId)->value('batch_id');
        if ($batchId === null) return null;
        // 在投影写通知及发出 notification.created 之前取批次锁，统一跨路径锁顺序。
        Db::table('system_notice_batches')->where('id', (int) $batchId)->lock(true)->find();
        return Db::table('system_notice_recipients')->where('issued_event_id', $eventId)->lock(true)->find();
    }

    public function completeProjection(int $eventId, int $notificationId): void
    {
        // 普通系统通知没有批次关系，保持原有行为。
        $projection = $this->lockProjection($eventId);
        if ($projection === null) return;
        $batchId = (int) $projection['batch_id'];
        $now = date('Y-m-d H:i:s');
        Db::table('system_notice_recipients')->where('issued_event_id', $eventId)->whereNull('projected_at')
            ->update(['notification_id' => $notificationId, 'projected_at' => $now]);
        if (Db::table('system_notice_recipients')->where('batch_id', (int) $batchId)->whereNull('projected_at')->count() === 0) {
            $this->update((int) $batchId, ['completed_at' => $now]);
        }
    }

    public function counts(int $batchId): array
    {
        $row = Db::table('system_notice_recipients')->where('batch_id', $batchId)
            ->field('SUM(notification_id > 0) AS generated_count, SUM(notification_id = 0) AS skipped_count')->select()->toArray()[0] ?? [];
        return ['generated' => (int) ($row['generated_count'] ?? 0), 'skipped' => (int) ($row['skipped_count'] ?? 0)];
    }

    public function pendingDeliveryIds(int $batchId): iterable
    {
        $after = 0;
        while (true) {
            $ids = array_map('intval', Db::table('system_notice_recipients')->where('batch_id', $batchId)
                ->whereNull('projected_at')->where('delivery_id', '>', $after)->order('delivery_id')->limit(500)->column('delivery_id'));
            if ($ids === []) return;
            foreach ($ids as $id) yield $id;
            $after = $ids[count($ids) - 1];
        }
    }

    private function hydrate(array $row): array
    {
        $selection = $row['selected_recipients'] ?? [];
        $row['selected_recipients'] = is_array($selection) ? $selection : json_decode((string) $selection, true, 512, JSON_THROW_ON_ERROR);
        return $row;
    }
}
