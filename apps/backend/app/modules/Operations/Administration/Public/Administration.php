<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Public;

use app\modules\Operations\Administration\Service\AdminReadModel;
use app\modules\Operations\SystemLifecycle\Application\SystemLifecycleCoordinator;
use app\shared\Observability\AuditLogger;
use think\facade\Db;

final class Administration
{
    public static function overview(): array { return app(AdminReadModel::class)->overview(); }
    public static function migrations(string $directory): array { return app(AdminReadModel::class)->migrations($directory); }
    public static function runMigrations(string $directory): array { return app(SystemLifecycleCoordinator::class)->run($directory, false); }
    public static function runtime(): array { return app(AdminReadModel::class)->runtime(); }
    public static function runtimeConfig(): array { return app(\app\modules\Operations\Runtime\Public\RuntimeCommunication::class)->snapshot(); }
    public static function validateRuntimeConfig(array $input): array { return app(\app\modules\Operations\Runtime\Public\RuntimeCommunication::class)->validate($input); }
    public static function applyRuntimeConfig(array $input, int $actorId): array { return app(\app\modules\Operations\Runtime\Public\RuntimeCommunication::class)->apply($input, $actorId); }
    public static function reconcileRuntime(): array { return app(\app\modules\Operations\Runtime\Public\RuntimeCommunication::class)->reconcile(); }
    public static function deliveries(array $params): array { return app(AdminReadModel::class)->deliveries($params); }
    public static function deliverySummary(): array { return app(AdminReadModel::class)->deliverySummary(); }
    public static function delivery(int $id): array { return app(AdminReadModel::class)->delivery($id); }
    public static function auditLogs(array $params): array { return app(AdminReadModel::class)->auditLogs($params); }
    public static function auditLog(int $id): array { return app(AdminReadModel::class)->auditLog($id); }
    public static function notificationsStats(): array { return app(AdminReadModel::class)->notificationsStats(); }
    public static function communityInsights(): array { return app(AdminReadModel::class)->communityInsights(); }
    public static function fileUsage(int $fileId): array { return app(AdminReadModel::class)->fileUsage($fileId); }

    public static function retryDelivery(int $deliveryId, int $actorId, string $ipAddress): void
    {
        self::withAudit(function () use ($deliveryId, $actorId, $ipAddress): void {
            $updated = Db::table('event_deliveries')->where('id', $deliveryId)->where('status', 'dead')->update([
                'status' => 'pending', 'available_at' => date('Y-m-d H:i:s.u'), 'claim_token' => null,
                'claimed_by' => null, 'lease_expires_at' => null, 'last_error' => null, 'completed_at' => null,
                'updated_at' => date('Y-m-d H:i:s.u'),
            ]);
            if ($updated !== 1) throw \app\api\ApiException::notFound('投递记录不存在或当前不可重试');
            app(AuditLogger::class)->record($actorId, 'eventing.delivery.retry', 'event_delivery', (string) $deliveryId, ['_ip' => $ipAddress]);
        });
    }

    private static function withAudit(callable $callback): void
    {
        Db::startTrans();
        try { $callback(); Db::commit(); }
        catch (\Throwable $exception) { Db::rollback(); throw $exception; }
    }
}
