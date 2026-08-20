<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Service;

use app\modules\Operations\Runtime\Application\ResolveFacilities;
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
            $worker['online'] = $heartbeat !== null && $heartbeat->modify('+90 seconds') >= $now;
        }
        unset($worker);

        $delivery = Db::table('event_deliveries')
            ->field("SUM(status = 'pending') AS pending, SUM(status = 'processing') AS processing, SUM(status = 'dead') AS dead, MAX(updated_at) AS last_updated")
            ->find();
        return [
            'facilities' => array_map(static fn ($status): array => $status->toArray(), $this->facilities->execute($this->facilityCatalog->configs())),
            'workers' => $workers,
            'eventing' => [
                'pending' => (int) ($delivery['pending'] ?? 0),
                'processing' => (int) ($delivery['processing'] ?? 0),
                'dead' => (int) ($delivery['dead'] ?? 0),
                'last_updated' => $delivery['last_updated'] ?? null,
            ],
        ];
    }

    /** @return array{data: array<int,array<string,mixed>>,total:int,per_page:int,current_page:int,last_page:int} */
    public function deliveries(array $params): array
    {
        $page = $this->page($params);
        $limit = $this->limit($params);
        $query = Db::table('event_deliveries')->alias('d')
            ->leftJoin('outbox_events e', 'e.id = d.event_id')
            ->field('d.id,d.event_id,d.handler_key,d.queue_name,d.execution_mode,d.status,d.attempt_count,d.available_at,d.last_error,d.created_at,d.updated_at,e.event_type,e.correlation_id');
        if (!empty($params['status'])) $query->where('d.status', (string) $params['status']);
        if (!empty($params['worker_type'])) $query->where('d.execution_mode', (string) $params['worker_type']);
        $total = (clone $query)->count('d.id');
        $rows = $query->order('d.id', 'desc')->page($page, $limit)->select()->toArray();
        return $this->paginate($rows, (int) $total, $page, $limit);
    }

    /** @return array{data: array<int,array<string,mixed>>,total:int,per_page:int,current_page:int,last_page:int} */
    public function auditLogs(array $params): array
    {
        $page = $this->page($params);
        $limit = $this->limit($params);
        $query = Db::table('audit_logs');
        foreach (['action', 'resource_type'] as $field) {
            if (!empty($params[$field])) $query->where($field, 'like', '%' . (string) $params[$field] . '%');
        }
        if (!empty($params['actor_id'])) $query->where('actor_id', (int) $params['actor_id']);
        $total = (clone $query)->count('id');
        $rows = $query->order('id', 'desc')->page($page, $limit)->select()->toArray();
        return $this->paginate($rows, (int) $total, $page, $limit);
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
