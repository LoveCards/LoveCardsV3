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
    public static function deliveries(array $params): array { return app(AdminReadModel::class)->deliveries($params); }
    public static function auditLogs(array $params): array { return app(AdminReadModel::class)->auditLogs($params); }
    public static function notificationsStats(): array { return app(AdminReadModel::class)->notificationsStats(); }
    public static function communityInsights(): array { return app(AdminReadModel::class)->communityInsights(); }
    public static function fileUsage(int $fileId): array { return app(AdminReadModel::class)->fileUsage($fileId); }

    public static function retryDelivery(int $deliveryId, int $actorId, string $reason, string $ipAddress): void
    {
        self::withAudit(function () use ($deliveryId, $actorId, $reason, $ipAddress): void {
            $updated = Db::table('event_deliveries')->where('id', $deliveryId)->whereIn('status', ['pending', 'dead'])->update([
                'status' => 'pending', 'available_at' => date('Y-m-d H:i:s.u'), 'claim_token' => null,
                'claimed_by' => null, 'lease_expires_at' => null, 'last_error' => null, 'completed_at' => null,
                'updated_at' => date('Y-m-d H:i:s.u'),
            ]);
            if ($updated !== 1) throw \app\api\ApiException::notFound('投递记录不存在或当前不可重试');
            app(AuditLogger::class)->record($actorId, 'eventing.delivery.retry', 'event_delivery', (string) $deliveryId, ['reason' => $reason, '_ip' => $ipAddress]);
        });
    }

    private static function withAudit(callable $callback): void
    {
        Db::startTrans();
        try { $callback(); Db::commit(); }
        catch (\Throwable $exception) { Db::rollback(); throw $exception; }
    }
}
