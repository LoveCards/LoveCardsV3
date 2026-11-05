<?php
declare(strict_types=1);

namespace app\modules\Platform\Safety\Application;

use app\api\ApiException;
use app\modules\Platform\Safety\Contract\AdminReportRepository;
use app\modules\Platform\Notifications\Contract\SystemNoticeIssuer;
use app\modules\Platform\Notifications\Domain\SystemNotice;
use app\shared\Observability\AuditLogger;
use app\shared\Transaction\TransactionManager;

/** 举报治理用例。普通管理查询只返回最小案件上下文，不打开私信/草稿正文。 */
final class AdminReports
{
    public function __construct(
        private AdminReportRepository $repository,
        private AuditLogger $audit,
        private TransactionManager $transactions,
        private SystemNoticeIssuer $notices
    ) {}

    public function list(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['list_rows'] ?? 20)));
        $result = $this->repository->list($params, $page, $limit);
        $rows = $result['items'];
        $total = $result['total'];
        foreach ($rows as &$row) {
            $row['target_id'] = (string) $row['target_id'];
            $row['details'] = $this->truncate((string) ($row['details'] ?? ''), 1000);
        }
        unset($row);
        return ['data' => $rows, 'total' => $total, 'per_page' => $limit, 'current_page' => $page, 'last_page' => max(1, (int) ceil($total / $limit))];
    }

    public function get(int $id): array
    {
        $report = $this->repository->find($id);
        if (!$report) throw ApiException::notFound('举报不存在');
        $report['target_id'] = (string) $report['target_id'];
        return $report;
    }

    public function transition(int $id, int $actorId, string $status, ?string $resolution, ?string $note, string $ipAddress): array
    {
        $status = strtolower(trim($status));
        $allowed = ['open', 'reviewing', 'resolved', 'rejected', 'closed'];
        if (!in_array($status, $allowed, true)) throw ApiException::badRequest('举报状态不合法');
        return $this->transactions->run(function () use ($id, $actorId, $status, $resolution, $note, $ipAddress): array {
            $report = $this->repository->lockForUpdate($id);
            if (!$report) throw ApiException::notFound('举报不存在');
            $from = (string) $report['status'];
            if ($from !== $status && !$this->canTransition($from, $status)) {
                throw ApiException::badRequest("不允许从 {$from} 转换为 {$status}");
            }
            // 真实结案必须写处理结论（会通知举报人）；同状态追加备注不受此限制。
            if ($from !== $status && in_array($status, ['resolved', 'rejected'], true)
                && ($resolution === null || trim($resolution) === '')) {
                throw ApiException::badRequest('请填写处理结论，再结束该举报。');
            }
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $timestamp = $now->format('Y-m-d H:i:s');
            $values = ['status' => $status, 'updated_at' => $timestamp];
            $assigned = false;
            if ($status !== 'open' && empty($report['assignee_id'])) {
                $values['assignee_id'] = $actorId;
                $assigned = true;
            }
            if (in_array($status, ['resolved', 'rejected', 'closed'], true)) $values['resolved_at'] = $timestamp;
            if ($resolution !== null) $values['resolution'] = substr(trim($resolution), 0, 255);
            $this->repository->update($id, $values);
            $this->repository->appendEvent($id, $actorId, $from === $status ? 'note' : 'status_changed', [
                'from_status' => $from, 'to_status' => $status, 'resolution' => $resolution, 'note' => $note,
                'assignee_id' => $assigned ? $actorId : ($report['assignee_id'] ?? null),
            ], $now);
            $this->audit->record($actorId, 'safety.report.transition', 'report', (string) $id, [
                'from_status' => $from, 'to_status' => $status, 'resolution' => $resolution, 'note' => $note,
                'assignee_id' => $assigned ? $actorId : ($report['assignee_id'] ?? null), '_ip' => $ipAddress,
            ]);
            if ($from !== $status && in_array($status, ['resolved', 'rejected'], true)) {
                $resolved = $status === 'resolved';
                $this->notices->issue(
                    (int) $report['reporter_id'],
                    'safety.report.outcome:' . $id . ':' . $status,
                    SystemNotice::MODERATION,
                    $resolved ? '你提交的举报已处理完成。' : '你提交的举报经审核后未予处理。',
                    null,
                    $resolved ? 'success' : 'info'
                );
            }
            return $this->get($id);
        });
    }

    private function canTransition(string $from, string $to): bool
    {
        return match ($from) {
            'open' => in_array($to, ['reviewing', 'rejected', 'closed'], true),
            'reviewing' => in_array($to, ['open', 'resolved', 'rejected'], true),
            'resolved', 'rejected' => $to === 'closed',
            default => false,
        };
    }

    private function truncate(string $value, int $length): string
    {
        return strlen($value) > $length ? substr($value, 0, $length) . '…' : $value;
    }
}
