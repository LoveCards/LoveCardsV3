<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Service;

use app\modules\Operations\Runtime\Application\ResolveFacilities;
use app\modules\Platform\Eventing\Public\EventConsumption;
use app\shared\Runtime\RuntimeFacilityCatalog;
use think\facade\Db;

/** 管理端只读投影。它返回聚合和运维元数据，不暴露用户私密明细。 */
final class AdminReadModel
{
    public function __construct(
        private ResolveFacilities $facilities,
        private RuntimeFacilityCatalog $facilityCatalog
    ) {}

    public function overview(): array
    {
        return [
            'users' => $this->count('users', static fn ($q) => $q->whereNull('deleted_at')),
            'cards' => $this->count('cards', static fn ($q) => $q->whereNull('deleted_at')),
            'comments' => $this->count('comments', static fn ($q) => $q->whereNull('deleted_at')),
            'reports_open' => $this->count('reports', static fn ($q) => $q->whereIn('status', ['open', 'reviewing'])),
            'notifications_unread' => $this->count('notifications', static fn ($q) => $q->whereNull('read_at')),
            'outbox_pending' => $this->count('event_deliveries', static fn ($q) => $q->whereIn('status', ['pending', 'processing'])),
            'files_pending' => $this->count('files', static fn ($q) => $q->where(function ($inner): void {
                $inner->where('upload_status', 'pending')->whereOr('status', 0);
            })),
            'runtime' => array_map(static fn ($status): array => $status->toArray(), $this->facilities->execute($this->facilityCatalog->configs())),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function migrations(string $directory): array
    {
        $source = app(\app\modules\Operations\SystemLifecycle\Contract\MigrationSource::class);
        $planner = app(\app\modules\Operations\SystemLifecycle\Application\MigrationPlanner::class);
        return $planner->plan($source->discover($directory))->toArray();
    }

    public function runtime(): array
    {
        $workers = Db::table('runtime_workers')->order('updated_at', 'desc')->limit(200)->select()->toArray();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        foreach ($workers as &$worker) {
            $heartbeat = isset($worker['heartbeat_at']) ? new \DateTimeImmutable((string) $worker['heartbeat_at'], new \DateTimeZone('UTC')) : null;
            $worker['online'] = ($worker['state'] ?? null) === 'running'
                && $heartbeat !== null
                && $heartbeat->modify('+90 seconds') >= $now;
        }
        unset($worker);

        $delivery = Db::table('event_deliveries')
            ->field("SUM(status = 'pending') AS pending, SUM(status = 'processing') AS processing, SUM(status = 'dead') AS dead, MIN(CASE WHEN status = 'pending' THEN available_at END) AS oldest_pending_at, SUM(status = 'processing' AND lease_expires_at < UTC_TIMESTAMP(6)) AS expired_processing, MAX(updated_at) AS last_updated")
            ->find();
        $oldestPendingAt = $delivery['oldest_pending_at'] ?? null;
        $oldestPendingAge = null;
        if (is_string($oldestPendingAt) && $oldestPendingAt !== '') {
            $oldestPendingAge = max(0, $now->getTimestamp() - (new \DateTimeImmutable($oldestPendingAt, new \DateTimeZone('UTC')))->getTimestamp());
        }
        $queues = Db::table('event_deliveries')
            ->field("queue_name,execution_mode,SUM(status = 'pending') AS pending,SUM(status = 'processing') AS processing,SUM(status = 'dead') AS dead")
            ->group('queue_name,execution_mode')
            ->order('queue_name', 'asc')
            ->select()
            ->toArray();
        $communication = app(\app\modules\Operations\Runtime\Public\RuntimeCommunication::class)->snapshot();
        return [
            'facilities' => array_map(static fn ($status): array => $status->toArray(), $this->facilities->execute($this->facilityCatalog->configs())),
            'workers' => $workers,
            'eventing' => [
                'pending' => (int) ($delivery['pending'] ?? 0),
                'processing' => (int) ($delivery['processing'] ?? 0),
                'dead' => (int) ($delivery['dead'] ?? 0),
                'oldest_pending_at' => $oldestPendingAt,
                'oldest_pending_age_seconds' => $oldestPendingAge,
                'expired_processing' => (int) ($delivery['expired_processing'] ?? 0),
                'queues' => $queues,
                'last_updated' => $delivery['last_updated'] ?? null,
            ],
            'communication' => $communication,
            'event_processing' => $this->eventProcessing($communication),
        ];
    }

    /** 事件模式与所选消费者的实际健康由同一快照展示。 */
    private function eventProcessing(array $communication): array
    {
        $consumer = (new EventConsumption())->current();
        if ($consumer === null) {
            return ['mode' => 'unknown', 'state' => 'unavailable', 'target_mode' => null, 'revision' => 0,
                'scheduled_trigger' => 'none', 'status' => 'degraded', 'reason_code' => 'event-consumer-unavailable', 'note' => null];
        }
        $mode = (string) ($consumer['mode'] ?? 'unknown');
        $probeKey = $mode === 'scheduled' ? 'scheduler' : 'event-worker';
        $probe = null;
        foreach (($communication['capabilities'] ?? []) as $item) {
            if (($item['key'] ?? null) === $probeKey) { $probe = $item; break; }
        }
        // Probe 缺失时保守报告不可用，不能由本模块直接调用 Runtime 内部实现。
        $probe ??= ['ready' => false, 'reason_code' => 'probe-unavailable', 'note' => null];
        $active = ($consumer['state'] ?? null) === 'active';
        $ready = $active && ($probe['ready'] ?? null) === true;
        return $consumer + [
            'status' => $ready ? 'ready' : 'degraded',
            'reason_code' => $ready ? null : (!$active ? 'event-consumer-draining' : ($probe['reason_code'] ?? 'probe-unavailable')),
            'note' => $probe['note'] ?? null,
        ];
    }

    /** @return array{data: array<int,array<string,mixed>>,total:int,per_page:int,current_page:int,last_page:int} */
    public function deliveries(array $params): array
    {
        $page = $this->page($params);
        $limit = $this->limit($params);
        $query = Db::table('event_deliveries')->alias('d')
            ->leftJoin('outbox_events e', 'e.id = d.event_id')
            ->field('d.id,d.event_id,d.handler_key,d.queue_name,d.execution_mode,d.status,d.attempt_count,d.available_at,d.last_error,d.policy_revision,d.policy_decision,d.created_at,d.updated_at,e.event_type,e.correlation_id');
        if (!empty($params['status'])) $query->where('d.status', (string) $params['status']);
        if (!empty($params['worker_type'])) $query->where('d.execution_mode', (string) $params['worker_type']);
        if (!empty($params['queue_name'])) $query->where('d.queue_name', (string) $params['queue_name']);
        if (!empty($params['policy_decision'])) $query->where('d.policy_decision', (string) $params['policy_decision']);
        $total = (clone $query)->count('d.id');
        $rows = $query->order('d.id', 'desc')->page($page, $limit)->select()->toArray();
        return $this->paginate($rows, (int) $total, $page, $limit);
    }

    /** @return array<string,mixed> */
    public function delivery(int $id): array
    {
        $row = Db::table('event_deliveries')->alias('d')
            ->leftJoin('outbox_events e', 'e.id = d.event_id')
            ->field('d.id,d.event_id,d.handler_key,d.queue_name,d.execution_mode,d.status,d.attempt_count,d.available_at,d.last_error,d.policy_revision,d.policy_decision,d.created_at,d.updated_at,e.event_type,e.correlation_id')
            ->where('d.id', $id)
            ->find();
        if (!$row) throw \app\api\ApiException::notFound('投递记录不存在');
        return $row;
    }

    /** 通知处理队列的当前累计状态，与列表的 queue_name=notifications 口径一致。 */
    public function deliverySummary(): array
    {
        $row = Db::table('event_deliveries')
            ->where('queue_name', 'notifications')
            ->field("SUM(status = 'pending') AS pending, SUM(status = 'processing') AS processing, SUM(status = 'dead') AS dead, SUM(status = 'completed' AND policy_decision = 'skipped') AS skipped")
            ->find();
        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'processing' => (int) ($row['processing'] ?? 0),
            'dead' => (int) ($row['dead'] ?? 0),
            'skipped' => (int) ($row['skipped'] ?? 0),
            'checked_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    /** @return array{data: array<int,array<string,mixed>>,total:int,per_page:int,current_page:int,last_page:int} */
    public function auditLogs(array $params): array
    {
        $page = $this->page($params);
        $limit = $this->limit($params);
        $query = Db::table('audit_logs')
            ->field('id,actor_id,action,resource_type,resource_id,request_id,correlation_id,created_at');
        if (isset($params['action'])) {
            $query->whereLike('action', addcslashes((string) $params['action'], '%_\\') . '%');
        }
        foreach (['resource_type', 'resource_id', 'request_id', 'correlation_id'] as $field) {
            if (!isset($params[$field])) continue;
            $value = trim((string) $params[$field]);
            if ($value === '') throw \app\api\ApiException::validation([$field => ['筛选值不能为空']]);
            $query->where($field, $value);
        }
        if (isset($params['actor']) && isset($params['actor_id'])) {
            throw \app\api\ApiException::validation(['actor' => ['不能同时指定操作者和旧版操作者 ID']]);
        }
        if (($params['actor'] ?? null) === 'system') {
            $query->whereNull('actor_id');
        } elseif (isset($params['actor'])) {
            $actorId = (int) $params['actor'];
            if ($actorId < 1 || $actorId > 2147483647) throw \app\api\ApiException::validation(['actor' => ['操作者 ID 无效']]);
            $query->where('actor_id', $actorId);
        } elseif (isset($params['actor_id'])) {
            $query->where('actor_id', (int) $params['actor_id']);
        }
        if (isset($params['from'], $params['to']) && strcmp((string) $params['from'], (string) $params['to']) >= 0) {
            throw \app\api\ApiException::validation(['to' => ['结束时间必须晚于开始时间']]);
        }
        if (isset($params['from'])) $query->where('created_at', '>=', (string) $params['from']);
        if (isset($params['to'])) $query->where('created_at', '<', (string) $params['to']);
        $total = (clone $query)->count('id');
        $rows = $query->order('id', 'desc')->page($page, $limit)->select()->toArray();
        return $this->paginate($rows, (int) $total, $page, $limit);
    }

    /** @return array<string,mixed> */
    public function auditLog(int $id): array
    {
        $row = Db::table('audit_logs')->where('id', $id)->find();
        if (!$row) throw \app\api\ApiException::notFound('审计记录不存在');
        return $row;
    }

    public function notificationsStats(): array
    {
        $row = Db::table('notifications')->field("COUNT(*) AS total, SUM(read_at IS NULL) AS unread, COUNT(DISTINCT recipient_id) AS recipients")->find();
        $types = Db::table('notifications')->field('type,COUNT(*) AS count')->group('type')->order('count', 'desc')->limit(30)->select()->toArray();
        return ['total' => (int) ($row['total'] ?? 0), 'unread' => (int) ($row['unread'] ?? 0), 'recipients' => (int) ($row['recipients'] ?? 0), 'types' => $types];
    }

    public function communityInsights(): array
    {
        $drafts = Db::table('card_drafts')->field('COUNT(*) AS total, SUM(deleted_at IS NULL) AS active')->find();
        $library = Db::table('library_items')->field('COUNT(*) AS total, COUNT(DISTINCT user_id) AS users')->find();
        $history = Db::table('view_history')->field('COUNT(*) AS total, COUNT(DISTINCT user_id) AS users')->find();
        return [
            'drafts' => ['total' => (int) ($drafts['total'] ?? 0), 'active' => (int) ($drafts['active'] ?? 0)],
            'library' => ['total' => (int) ($library['total'] ?? 0), 'users' => (int) ($library['users'] ?? 0)],
            'history' => ['total' => (int) ($history['total'] ?? 0), 'users' => (int) ($history['users'] ?? 0)],
            'privacy' => ['mode' => 'aggregate_only', 'private_content_exposed' => false],
        ];
    }

    public function fileUsage(int $fileId): array
    {
        $file = Db::table('files')->where('id', $fileId)->field(implode(',', [
            'id', 'hash', 'channel_slug', 'user_id', 'is_public', 'scene', 'original_name',
            'file_url', 'file_size', 'file_ext', 'mime_type', 'status', 'upload_status',
            'expire_at', 'purge_status', 'purge_attempts', 'purge_requested_at',
            'purge_storage_deleted_at', 'purge_error', 'created_at', 'updated_at', 'deleted_at',
        ]))->find();
        if (!$file) throw \app\api\ApiException::notFound('文件不存在');
        $references = Db::table('file_references')
            ->where('file_id', $fileId)
            ->field('resource_type,resource_id,purpose,is_public,created_at')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        foreach ($references as &$reference) {
            $reference['resource_id'] = (string) $reference['resource_id'];
            $reference['is_public'] = (bool) $reference['is_public'];
        }
        unset($reference);
        return ['file' => $file, 'references' => $references, 'orphaned' => $references === []];
    }

    private function count(string $table, callable $callback): int
    {
        $query = Db::table($table);
        $callback($query);
        return (int) $query->count();
    }

    private function page(array $params): int { return max(1, (int) ($params['page'] ?? 1)); }
    private function limit(array $params): int { return min(100, max(1, (int) ($params['list_rows'] ?? 20))); }
    private function paginate(array $rows, int $total, int $page, int $limit): array
    {
        return ['data' => $rows, 'total' => $total, 'per_page' => $limit, 'current_page' => $page, 'last_page' => max(1, (int) ceil($total / $limit))];
    }
}
