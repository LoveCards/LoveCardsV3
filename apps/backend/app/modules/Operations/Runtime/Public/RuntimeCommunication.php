<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Public;

use app\modules\Operations\Runtime\Application\ResolveRuntimeCommunication;
use app\modules\Operations\Runtime\Application\UpdateRuntimeConfig;
use app\modules\Operations\Runtime\Contract\RuntimeConfigStore;
use app\modules\Operations\Runtime\Infrastructure\EventingWorkerProbe;
use app\modules\Operations\Runtime\Infrastructure\RealtimeGatewayProbe;
use app\modules\Operations\Runtime\Infrastructure\RedisRealtimeProbe;
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
    /** 事务内读取模式，禁止触发外部设施探测。 */
    public function selectedMode(): string
    {
        $mode = $this->store()->current()['desired_mode'] ?? null;
        return in_array($mode, ['polling', 'realtime'], true) ? $mode : 'unknown';
    }

    /** 必须在调用者的业务事务内使用，使入队与模式切换串行。 */
    public function selectedModeForEnqueue(): string
    {
        $mode = $this->store()->selectedModeForEnqueue();
        return in_array($mode, ['polling', 'realtime'], true) ? $mode : 'unknown';
    }

    public function snapshot(): array
    {
        return $this->resolver()->snapshot();
    }

    /**
     * 浏览器通信客户端所需的模式快照。
     * 只暴露节奏与所选模式，不暴露设施细节。
     *
     * @return array{desired_mode: string, effective_mode: string, status: string, settings: array<string, mixed>}
     */
    public function browserSettings(): array
    {
        $snapshot = $this->snapshot();
        $settings = $snapshot['settings'];
        return [
            'desired_mode' => (string) $snapshot['desired_mode'],
            'effective_mode' => (string) $snapshot['effective_mode'],
            'status' => (string) $snapshot['status'],
            'settings' => [
                'notification_interval_seconds' => (int) $settings['notification_interval_seconds'],
                'message_interval_seconds' => (int) $settings['message_interval_seconds'],
                'unread_interval_seconds' => (int) $settings['unread_interval_seconds'],
                'polling_backoff_factor' => (float) $settings['polling_backoff_factor'],
                'polling_max_interval_seconds' => (int) $settings['polling_max_interval_seconds'],
                'pause_when_hidden' => (bool) $settings['pause_when_hidden'],
            ],
        ];
    }

    /** Eventing 实时信号投递门禁：deliver、pause、skip 三态。 */
    public function signalDeliveryState(): string
    {
        // 默认轮询不做外部网络探测；配置不可读时也不能误判为可跳过。
        $selected = $this->selectedMode();
        if ($selected === 'unknown') return 'pause';
        if ($selected === 'polling') return 'skip';
        $snapshot = $this->snapshot();
        if ($snapshot['desired_mode'] !== 'realtime') return 'pause';
        return $snapshot['status'] === 'ready' ? 'deliver' : 'pause';
    }

    public function validate(array $input): array
    {
        return $this->updater()->validate($input);
    }

    /** $afterUpdate 与配置 CAS、审计在同一事务内执行，供 Host 编排关联状态。 */
    public function apply(array $input, int $actorId, ?callable $afterUpdate = null): array
    {
        return $this->updater()->apply($actorId, $input, static function (int $uid, array $payload) use ($afterUpdate): void {
            if ($afterUpdate !== null) $afterUpdate($payload);
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
            [new RedisRealtimeProbe(), new EventingWorkerProbe(), new RealtimeGatewayProbe(), new SchedulerRunProbe(), new SupervisorPolicyProbe()]
        );
    }

    private function updater(): UpdateRuntimeConfig
    {
        return new UpdateRuntimeConfig($this->store(), $this->resolver());
    }
}
