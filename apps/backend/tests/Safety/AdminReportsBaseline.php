<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/api/ApiException.php';
require $root . '/app/shared/Observability/AuditLogger.php';
require $root . '/app/shared/Transaction/TransactionManager.php';
require $root . '/app/modules/Platform/Safety/Contract/AdminReportRepository.php';
require $root . '/app/modules/Platform/Safety/Application/AdminReports.php';

use app\modules\Platform\Safety\Application\AdminReports;
use app\modules\Platform\Safety\Contract\AdminReportRepository;
use app\shared\Observability\AuditLogger;
use app\shared\Transaction\TransactionManager;

final class FakeAdminReportsRepository implements AdminReportRepository
{
    public array $report = [
        'id' => 4, 'reporter_id' => 2, 'target_type' => 'comment', 'target_id' => '8',
        'reason' => 'abuse', 'details' => '', 'status' => 'open', 'assignee_id' => null,
        'resolution' => null, 'created_at' => '2026-08-19 00:00:00',
        'updated_at' => '2026-08-19 00:00:00', 'resolved_at' => null, 'events' => [],
    ];
    public array $events = [];
    public function list(array $filters, int $page, int $limit): array { return ['items' => [$this->report], 'total' => 1]; }
    public function find(int $reportId): ?array { return $reportId === 4 ? $this->report + ['events' => $this->events] : null; }
    public function lockForUpdate(int $reportId): ?array { return $this->find($reportId); }
    public function update(int $reportId, array $values): void { $this->report = array_merge($this->report, $values); }
    public function appendEvent(int $reportId, int $actorId, string $eventType, array $payload, DateTimeImmutable $at): void
    {
        $this->events[] = ['report_id' => $reportId, 'actor_id' => $actorId, 'event_type' => $eventType, 'payload' => $payload];
    }
}

final class FakeAdminReportsAudit implements AuditLogger
{
    public array $records = [];
    public function record(?int $actorId, string $action, string $resourceType, ?string $resourceId, array $metadata = []): void
    {
        $this->records[] = [$actorId, $action, $resourceType, $resourceId, $metadata];
    }
}

final class FakeAdminReportsTransactions implements TransactionManager
{
    public int $calls = 0;
    public function run(callable $callback): mixed { $this->calls++; return $callback(); }
}

$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$repository = new FakeAdminReportsRepository();
$audit = new FakeAdminReportsAudit();
$transactions = new FakeAdminReportsTransactions();
$reports = new AdminReports($repository, $audit, $transactions);

$result = $reports->transition(4, 9, 'reviewing', '开始审核', null, 'triage', '127.0.0.1');
$assert($result['assignee_id'] === 9, 'first non-open transition must assign the acting reviewer');
$assert($repository->events[0]['payload']['assignee_id'] === 9, 'assignee must be captured in report history');
$assert($audit->records[0][1] === 'safety.report.transition', 'report transition must be audited in the transaction');
$assert($transactions->calls === 1, 'report transition must use the transaction boundary');

$invalid = false;
try { $reports->transition(4, 9, 'open', 'invalid reverse', null, null, ''); }
catch (\app\api\ApiException) { $invalid = true; }
$assert(!$invalid, 'reviewing may return to open according to the report state machine');

fwrite(STDOUT, "PASS admin reports baseline\n");
