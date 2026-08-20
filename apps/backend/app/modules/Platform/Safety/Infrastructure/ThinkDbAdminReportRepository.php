<?php
declare(strict_types=1);

namespace app\modules\Platform\Safety\Infrastructure;

use app\modules\Platform\Safety\Contract\AdminReportRepository;
use think\facade\Db;

final class ThinkDbAdminReportRepository implements AdminReportRepository
{
    public function list(array $filters, int $page, int $limit): array
    {
        $query = Db::table('reports')->field(implode(',', [
            'id', 'reporter_id', 'target_type', 'target_id', 'reason', 'details', 'status',
            'assignee_id', 'resolution', 'created_at', 'updated_at', 'resolved_at',
        ]));
        if (!empty($filters['status'])) $query->where('status', (string) $filters['status']);
        if (!empty($filters['resource_type'])) $query->where('target_type', (string) $filters['resource_type']);
        if (!empty($filters['search_value'])) {
            $value = '%' . (string) $filters['search_value'] . '%';
            $query->where(function ($inner) use ($value): void {
                $inner->whereLike('reason', $value)->whereOrLike('details', $value);
            });
        }

        $total = (int) (clone $query)->count('id');
        $items = $query->order('updated_at', 'desc')->order('id', 'desc')->page($page, $limit)->select()->toArray();
        return ['items' => $items, 'total' => $total];
    }

    public function find(int $reportId): ?array
    {
        $report = Db::table('reports')->where('id', $reportId)->find();
        if ($report === null) return null;
        if (isset($report['evidence_snapshot']) && is_string($report['evidence_snapshot'])) {
            $decoded = json_decode($report['evidence_snapshot'], true);
            $report['evidence_snapshot'] = is_array($decoded) ? $decoded : null;
        }
        $report['events'] = Db::table('report_events')
            ->where('report_id', $reportId)
            ->order('id', 'asc')
            ->select()
            ->toArray();
        return $report;
    }

    public function lockForUpdate(int $reportId): ?array
    {
        return Db::table('reports')->where('id', $reportId)->lock(true)->find();
    }

    public function update(int $reportId, array $values): void
    {
        Db::table('reports')->where('id', $reportId)->update($values);
    }

    public function appendEvent(int $reportId, int $actorId, string $eventType, array $payload, \DateTimeImmutable $at): void
    {
        Db::table('report_events')->insert([
            'report_id' => $reportId,
            'actor_id' => $actorId,
            'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
    }
}
