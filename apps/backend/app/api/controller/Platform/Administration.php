<?php
declare(strict_types=1);

namespace app\api\controller\Platform;

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Operations\Administration\Public\Administration as Admin;
use app\modules\Platform\Auth\Application\AdminSecurity;
use app\modules\Platform\Safety\Public\Safety;
use app\shared\Observability\AuditLogger;
use app\shared\Transport\Http\UploadLimitDiagnostics;
use app\modules\Operations\SystemLifecycle\Application\UpgradeCoordinator;
use app\modules\Operations\SystemLifecycle\Exception\UnsettledUpgradePlanException;

final class Administration extends BaseController
{
    private function uid(): int { return (int) request()->auth->uid(); }
    private function ip(): string { return (string) request()->ip(); }
    private function migrationsDirectory(): string { return app()->getRootPath() . 'database' . DIRECTORY_SEPARATOR . 'migrations'; }

    public function overview() { return $this->respond(Admin::overview()); }
    public function migrations() { return $this->respond(Admin::migrations($this->migrationsDirectory())); }

    public function runMigrations()
    {
        $params = $this->validated();
        $reason = trim((string) ($params['reason'] ?? ''));
        app(AuditLogger::class)->record($this->uid(), 'system.migration.requested', 'system_migration', null, ['reason' => $reason, '_ip' => request()->ip()]);
        try {
            $result = Admin::runMigrations($this->migrationsDirectory());
        } catch (UnsettledUpgradePlanException $exception) {
            throw ApiException::badRequest($exception->getMessage());
        }
        app(AuditLogger::class)->record($this->uid(), 'system.migration.executed', 'system_migration', null, ['result_count' => count($result), 'reason' => $reason, '_ip' => request()->ip()]);
        return $this->respond($result);
    }

    public function runtime() { return $this->respond(Admin::runtime()); }
    public function deliveries() { return $this->respond(Admin::deliveries($this->validated())); }
    public function retryDelivery(int $id)
    {
        $params = $this->validated();
        Admin::retryDelivery($id, $this->uid(), $this->nonBlankReason((string) ($params['reason'] ?? '')), $this->ip());
        return $this->respond();
    }
    public function auditLogs() { return $this->respond(Admin::auditLogs($this->validated())); }
    public function notificationStats() { return $this->respond(Admin::notificationsStats()); }
    public function upgrades() { $params=$this->validated(); return $this->respond(array_map(static fn($plan) => $plan->toArray(), app(UpgradeCoordinator::class)->recent((int)($params['limit'] ?? 50)))); }
    public function upgrade(string $planId) { $plan=app(UpgradeCoordinator::class)->find($planId); if($plan===null) throw ApiException::notFound('升级计划不存在'); return $this->respond($plan->toArray()); }
    public function createUpgrade()
    {
        $file = request()->file('file');
        $uploadLimit = UploadLimitDiagnostics::detect();
        if ($uploadLimit !== null) {
            throw ApiException::error(
                $uploadLimit['message'],
                ApiException::CODE_FILE_TOO_LARGE,
                $uploadLimit
            );
        }
        $params = $this->validated(null, ['file' => $file]);
        $reason = $this->nonBlankReason((string) $params['reason']);
        $path = is_object($params['file'] ?? null) && method_exists($params['file'], 'getRealPath')
            ? (string) $params['file']->getRealPath()
            : '';
        if ($path === '' || !is_file($path)) throw ApiException::badRequest('Release ZIP 上传失败');
        $this->auditUpgrade('system.upgrade.create.requested', null, $reason, [
            'filename' => method_exists($params['file'], 'getOriginalName') ? (string) $params['file']->getOriginalName() : 'release.zip',
            'size_bytes' => method_exists($params['file'], 'getSize') ? (int) $params['file']->getSize() : null,
        ]);
        try { $plan = app(UpgradeCoordinator::class)->createPlan($path, $this->uid()); }
        catch (\InvalidArgumentException $exception) { throw ApiException::badRequest($exception->getMessage()); }
        $this->auditUpgrade('system.upgrade.created', $plan->plan()->planId(), $reason, ['release_id' => $plan->plan()->releaseId()]);
        return $this->respond($plan->toArray());
    }
    public function advanceUpgrade(string $planId)
    {
        $params = $this->validated();
        $reason = $this->nonBlankReason((string) ($params['reason'] ?? ''));
        $coordinator = app(UpgradeCoordinator::class);
        if ($coordinator->find($planId) === null) throw ApiException::notFound('升级计划不存在');
        $this->auditUpgrade('system.upgrade.advance.requested', $planId, $reason);
        try { $plan = $coordinator->advance($planId); }
        catch (\LogicException|\InvalidArgumentException $exception) { throw ApiException::badRequest($exception->getMessage()); }
        $this->auditUpgrade('system.upgrade.advanced', $planId, $reason, ['status' => $plan->plan()->status(), 'revision' => $plan->plan()->revision()]);
        return $this->respond($plan->toArray());
    }
    public function approveUpgradeContract(string $planId)
    {
        $params = $this->validated();
        $reason = $this->nonBlankReason((string) ($params['reason'] ?? ''));
        $coordinator = app(UpgradeCoordinator::class);
        if ($coordinator->find($planId) === null) throw ApiException::notFound('升级计划不存在');
        $this->auditUpgrade('system.upgrade.contract_approval.requested', $planId, $reason);
        try { $plan = $coordinator->approveContract($planId); }
        catch (\LogicException|\InvalidArgumentException $exception) { throw ApiException::badRequest($exception->getMessage()); }
        $this->auditUpgrade('system.upgrade.contract_approved', $planId, $reason, ['revision' => $plan->plan()->revision()]);
        return $this->respond($plan->toArray());
    }
    public function recoverUpgrade(string $planId)
    {
        $params = $this->validated();
        $reason = $this->nonBlankReason((string) ($params['reason'] ?? ''));
        $coordinator = app(UpgradeCoordinator::class);
        if ($coordinator->find($planId) === null) throw ApiException::notFound('升级计划不存在');
        $this->auditUpgrade('system.upgrade.recovery.requested', $planId, $reason);
        try { $plan = $coordinator->recover($planId); }
        catch (\LogicException|\InvalidArgumentException $exception) { throw ApiException::badRequest($exception->getMessage()); }
        $this->auditUpgrade('system.upgrade.recovered', $planId, $reason, ['revision' => $plan->plan()->revision()]);
        return $this->respond($plan->toArray());
    }
    public function communityInsights() { return $this->respond(Admin::communityInsights()); }
    public function fileUsage(int $id) { return $this->respond(Admin::fileUsage($id)); }

    public function reports() { return $this->respond(Safety::adminReports()->list($this->validated())); }
    public function report(int $id) { return $this->respond(Safety::adminReports()->get($id)); }
    public function transitionReport(int $id)
    {
        $params = $this->validated();
        $reason = trim((string) ($params['resolution'] ?? $params['note'] ?? ''));
        if ($reason === '') throw ApiException::badRequest('处理举报必须填写原因');
        return $this->respond(Safety::adminReports()->transition(
            $id, $this->uid(), (string) $params['status'], $reason,
            isset($params['resolution']) ? (string) $params['resolution'] : null,
            isset($params['note']) ? (string) $params['note'] : null,
            $this->ip()
        ));
    }

    public function userSecurity(int $id) { return $this->respond(app(AdminSecurity::class)->overview($id)); }
    public function sessions(int $id) { return $this->respond(app(AdminSecurity::class)->sessions($id)); }
    public function revokeSession(int $id, int $sessionId)
    {
        $params = $this->validated();
        app(AdminSecurity::class)->revoke($this->uid(), $id, $sessionId, $this->nonBlankReason((string) ($params['reason'] ?? '')), $this->ip());
        return $this->respond();
    }
    public function revokeSessions(int $id)
    {
        $params = $this->validated();
        app(AdminSecurity::class)->revokeAll($this->uid(), $id, $this->nonBlankReason((string) ($params['reason'] ?? '')), $this->ip());
        return $this->respond();
    }
    public function recovery() { return $this->respond(app(AdminSecurity::class)->recovery($this->validated())); }

    /** @param array<string,mixed> $metadata */
    private function auditUpgrade(string $action, ?string $planId, string $reason, array $metadata = []): void
    {
        app(AuditLogger::class)->record($this->uid(), $action, 'system_upgrade', $planId, $metadata + ['reason' => $reason, '_ip' => request()->ip()]);
    }

    private function nonBlankReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') throw ApiException::badRequest('操作原因不能为空');
        return $reason;
    }
}
