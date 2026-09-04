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
        // Execute the contract guard that rejects legacy truthy migration flags.
        // The public request itself has no writable fields.
        $this->validated();
        app(AuditLogger::class)->record($this->uid(), 'system.migration.requested', 'system_migration', null, ['_ip' => request()->ip()]);
        try {
            $result = Admin::runMigrations($this->migrationsDirectory());
        } catch (UnsettledUpgradePlanException $exception) {
            throw ApiException::badRequest($exception->getMessage());
        }
        app(AuditLogger::class)->record($this->uid(), 'system.migration.executed', 'system_migration', null, ['result_count' => count($result), '_ip' => request()->ip()]);
        return $this->respond($result);
    }

    public function runtime() { return $this->respond(Admin::runtime()); }
    public function deliveries() { return $this->respond(Admin::deliveries($this->validated())); }
    public function delivery(int $id) { return $this->respond(Admin::delivery($id)); }
    public function retryDelivery(int $id)
    {
        Admin::retryDelivery($id, $this->uid(), $this->ip());
        return $this->respond();
    }
    public function auditLogs() { return $this->respond(Admin::auditLogs($this->validated())); }
    public function auditLog(int $id) { return $this->respond(Admin::auditLog($id)); }
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
        $path = is_object($params['file'] ?? null) && method_exists($params['file'], 'getRealPath')
            ? (string) $params['file']->getRealPath()
            : '';
        if ($path === '' || !is_file($path)) throw ApiException::badRequest('Release ZIP 上传失败');
        $this->auditUpgrade('system.upgrade.create.requested', null, [
            'filename' => method_exists($params['file'], 'getOriginalName') ? (string) $params['file']->getOriginalName() : 'release.zip',
            'size_bytes' => method_exists($params['file'], 'getSize') ? (int) $params['file']->getSize() : null,
        ]);
        try { $plan = app(UpgradeCoordinator::class)->createPlan($path, $this->uid()); }
        catch (\InvalidArgumentException $exception) { throw ApiException::badRequest($exception->getMessage()); }
        $this->auditUpgrade('system.upgrade.created', $plan->plan()->planId(), ['release_id' => $plan->plan()->releaseId()]);
        return $this->respond($plan->toArray());
    }
    public function advanceUpgrade(string $planId)
    {
        $coordinator = app(UpgradeCoordinator::class);
        if ($coordinator->find($planId) === null) throw ApiException::notFound('升级计划不存在');
        $this->auditUpgrade('system.upgrade.advance.requested', $planId);
        try { $plan = $coordinator->advance($planId); }
        catch (\LogicException|\InvalidArgumentException $exception) { throw ApiException::badRequest($exception->getMessage()); }
        $this->auditUpgrade('system.upgrade.advanced', $planId, ['status' => $plan->plan()->status(), 'revision' => $plan->plan()->revision()]);
        return $this->respond($plan->toArray());
    }
    public function approveUpgradeContract(string $planId)
    {
        $coordinator = app(UpgradeCoordinator::class);
        if ($coordinator->find($planId) === null) throw ApiException::notFound('升级计划不存在');
        $this->auditUpgrade('system.upgrade.contract_approval.requested', $planId);
        try { $plan = $coordinator->approveContract($planId); }
        catch (\LogicException|\InvalidArgumentException $exception) { throw ApiException::badRequest($exception->getMessage()); }
        $this->auditUpgrade('system.upgrade.contract_approved', $planId, ['revision' => $plan->plan()->revision()]);
        return $this->respond($plan->toArray());
    }
    public function recoverUpgrade(string $planId)
    {
        $coordinator = app(UpgradeCoordinator::class);
        if ($coordinator->find($planId) === null) throw ApiException::notFound('升级计划不存在');
        $this->auditUpgrade('system.upgrade.recovery.requested', $planId);
        try { $plan = $coordinator->recover($planId); }
        catch (\LogicException|\InvalidArgumentException $exception) { throw ApiException::badRequest($exception->getMessage()); }
        $this->auditUpgrade('system.upgrade.recovered', $planId, ['revision' => $plan->plan()->revision()]);
        return $this->respond($plan->toArray());
    }
    public function communityInsights() { return $this->respond(Admin::communityInsights()); }
    public function fileUsage(int $id) { return $this->respond(Admin::fileUsage($id)); }

    public function reports() { return $this->respond(Safety::adminReports()->list($this->validated())); }
    public function report(int $id) { return $this->respond(Safety::adminReports()->get($id)); }
    public function transitionReport(int $id)
    {
        $params = $this->validated();
        return $this->respond(Safety::adminReports()->transition(
            $id, $this->uid(), (string) $params['status'],
            isset($params['resolution']) ? (string) $params['resolution'] : null,
            isset($params['note']) ? (string) $params['note'] : null,
            $this->ip()
        ));
    }

    public function userSecurity(int $id)
    {
        $auth = request()->auth;
        return $this->respond(app(AdminSecurity::class)->overview(
            $auth->uid(),
            $auth->roleIds(),
            $auth->grants(),
            $id
        ));
    }
    public function sessions(int $id) { return $this->respond(app(AdminSecurity::class)->sessions($id)); }
    public function revokeSession(int $id, int $sessionId)
    {
        $auth = request()->auth;
        app(AdminSecurity::class)->revoke(
            $auth->uid(),
            $auth->roleIds(),
            $auth->grants(),
            $id,
            $sessionId,
            $this->ip()
        );
        return $this->respond();
    }
    public function revokeSessions(int $id)
    {
        $auth = request()->auth;
        app(AdminSecurity::class)->revokeAll(
            $auth->uid(),
            $auth->roleIds(),
            $auth->grants(),
            $id,
            $this->ip()
        );
        return $this->respond();
    }
    public function recovery() { return $this->respond(app(AdminSecurity::class)->recovery($this->validated())); }

    /** @param array<string,mixed> $metadata */
    private function auditUpgrade(string $action, ?string $planId, array $metadata = []): void
    {
        app(AuditLogger::class)->record($this->uid(), $action, 'system_upgrade', $planId, $metadata + ['_ip' => request()->ip()]);
    }
}
