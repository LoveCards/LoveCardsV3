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
use app\modules\Operations\Runtime\Infrastructure\EventConsumerModeProbe;
use app\modules\Operations\Runtime\Infrastructure\SystemRuntimeClock;
use app\modules\Operations\Runtime\Infrastructure\ThinkOrmRuntimeConfigStore;
use app\modules\Platform\Eventing\Public\EventConsumption;
use app\shared\Observability\AuditLogger;

/**
 * 通信运行时对外门面：快照、校验、应用、对账。
 *
 * 业务模块不得依赖本门面判断通信模式——模式感知只允许出现在运行时控制面。
 * 组装集中在门面内（probe 集合是控制面私有细节），provider 只绑存储端口。
 */
final class RuntimeCommunication
{
    public static function assertWorkersStopped(): void
    {
        if (\think\facade\Db::table('runtime_workers')->where('worker_type', 'eventing')->where('state', '<>', 'stopped')->count() > 0) {
            throw new \RuntimeException('仍有未确认停止的 Event Worker，禁止 Contract；过期心跳不能证明进程已退出');
        }
    }

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

    /** CLI 与管理端共用同一模式及健康快照，避免旧 Facility fallback 混入状态。 */
    public function statusSnapshot(): array
    {
        $communication = $this->snapshot();
        $consumer = app(EventConsumption::class)->current();
        if ($consumer === null) {
            $eventProcessing = ['mode' => 'unknown', 'state' => 'unavailable', 'target_mode' => null,
                'revision' => 0, 'scheduled_trigger' => 'none', 'status' => 'degraded',
                'reason_code' => 'event-consumer-unavailable', 'note' => null];
        } else {
            $probeKey = $consumer['mode'] === 'scheduled' ? 'scheduler' : 'event-worker';
            $probe = null;
            foreach ($communication['capabilities'] ?? [] as $item) {
                if (($item['key'] ?? null) === $probeKey) { $probe = $item; break; }
            }
            $probe ??= ['ready' => false, 'reason_code' => 'probe-unavailable', 'note' => null];
            $active = ($consumer['state'] ?? null) === 'active';
            $ready = $active && ($probe['ready'] ?? null) === true;
            $eventProcessing = $consumer + [
                'status' => $ready ? 'ready' : 'degraded',
                'reason_code' => $ready ? null : (!$active ? 'event-consumer-draining' : ($probe['reason_code'] ?? 'probe-unavailable')),
                'note' => $probe['note'] ?? null,
            ];
        }
        return ['communication' => $communication, 'event_processing' => $eventProcessing];
    }

    /** 升级健康 Gate：要求新 Release 的 Worker 心跳，不接受旧进程代答。 */
    public function eventWorkerReadyForRelease(string $releaseId): bool
    {
        $row = $this->store()->current();
        if ($row === null || $releaseId === '') return false;
        $settings = \app\modules\Operations\Runtime\Domain\CommunicationSettings::normalize($row['settings']);
        $settings['required_release_id'] = $releaseId;
        $settings['require_realtime_queue'] = false;
        return (new EventingWorkerProbe())->probe($settings)['ready'] === true;
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
            [new EventConsumerModeProbe(), new RedisRealtimeProbe(), new EventingWorkerProbe(), new RealtimeGatewayProbe(), new SchedulerRunProbe()]
        );
    }

    private function updater(): UpdateRuntimeConfig
    {
        return new UpdateRuntimeConfig($this->store(), $this->resolver(), static fn (): bool => (new EventConsumption())->lockResidentForRealtime());
    }
}
