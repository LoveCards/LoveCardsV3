<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Public;

use app\modules\Operations\Runtime\Application\ResolveRuntimeCommunication;
use app\modules\Operations\Runtime\Application\UpdateRuntimeConfig;
use app\modules\Operations\Runtime\Contract\RuntimeConfigStore;
use app\modules\Operations\Runtime\Infrastructure\EventingWorkerProbe;
use app\modules\Operations\Runtime\Infrastructure\RealtimeGatewayProbe;
use app\modules\Operations\Runtime\Infrastructure\SchedulerRunProbe;
use app\modules\Operations\Runtime\Infrastructure\SupervisorPolicyProbe;
use app\modules\Operations\Runtime\Infrastructure\SystemRuntimeClock;
use app\modules\Operations\Runtime\Infrastructure\ThinkOrmRuntimeConfigStore;
use app\shared\Observability\AuditLogger;

/**
 * 通信运行时对外门面：快照、校验、应用、对账。
 *
 * 业务模块不得依赖本门面判断通信模式——模式感知只允许出现在运行时控制面。
 * 组装集中在门面内（probe 集合是控制面私有细节），provider 只绑存储端口。
 */
final class RuntimeCommunication
{
    public function snapshot(): array
    {
        return $this->resolver()->snapshot();
    }

    public function validate(array $input): array
    {
        return $this->updater()->validate($input);
    }

    public function apply(array $input, int $actorId): array
    {
        return $this->updater()->apply($actorId, $input, static function (int $uid, array $payload): void {
            app(AuditLogger::class)->record($uid, 'runtime.communication.configure', 'runtime_config', '1', $payload);
        });
    }

    /** 对账：清缓存重探后返回新快照（不修改配置行）。 */
    public function reconcile(): array
    {
        RealtimeGatewayProbe::resetCache();
        return $this->snapshot();
    }

    private function store(): RuntimeConfigStore
    {
        $bound = app(RuntimeConfigStore::class);
        return $bound instanceof RuntimeConfigStore ? $bound : new ThinkOrmRuntimeConfigStore();
    }

    private function resolver(): ResolveRuntimeCommunication
    {
        return new ResolveRuntimeCommunication(
            $this->store(),
            new SystemRuntimeClock(),
            [new EventingWorkerProbe(), new RealtimeGatewayProbe(), new SchedulerRunProbe(), new SupervisorPolicyProbe()]
        );
    }

    private function updater(): UpdateRuntimeConfig
    {
        return new UpdateRuntimeConfig($this->store(), $this->resolver());
    }
}
