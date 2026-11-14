<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Application;

use app\api\ApiException;
use app\modules\Operations\Runtime\Contract\RuntimeClock;
use app\modules\Operations\Runtime\Contract\RuntimeConfigStore;
use app\modules\Operations\Runtime\Contract\RuntimeProbe;
use app\modules\Operations\Runtime\Domain\CommunicationSettings;

/**
 * 通信运行时 Resolver：模式来自持久化选择，probe 只派生健康状态。
 *
 * 实时硬依赖为常驻事件消费、Redis、Event Worker 和 Gateway。
 * Scheduler 仅供诊断。故障时保持 realtime/degraded，不自动切换轮询。
 */
final class ResolveRuntimeCommunication
{
    /** realtime 能力判定顺序，第一个失败项决定 reason_code。 */
    private const BLOCKING_KEYS = ['event-consumer', 'realtime-redis', 'event-worker', 'realtime-gateway'];

    public function __construct(
        private RuntimeConfigStore $store,
        private RuntimeClock $clock,
        private array $probes
    ) {
        foreach ($this->probes as $probe) {
            if (!$probe instanceof RuntimeProbe) throw new \LogicException('Runtime probes must implement RuntimeProbe');
        }
    }

    public function snapshot(): array
    {
        $checkedAt = $this->clock->nowUtc();
        try {
            $row = $this->store->current();
        } catch (\Throwable $ignored) {
            $row = null;
        }
        // 默认选择由安装持久初始化。运行期缺失或损坏不能变成管理员选择了轮询。
        if ($row === null || !in_array($row['desired_mode'] ?? null, CommunicationSettings::MODES, true)
            || !is_array($row['settings'] ?? null) || (int) ($row['revision'] ?? 0) < 1
            || CommunicationSettings::validateInput(array_intersect_key(
                $row['settings'], array_flip(CommunicationSettings::INPUT_KEYS)
            )) !== []) {
            throw ApiException::capabilityUnavailable('通信配置暂不可用，请稍后重试', 'runtime-config-unavailable');
        }

        $settings = CommunicationSettings::normalize($row['settings']);
        $capabilities = $this->probeCapabilities($settings, $checkedAt);
        $desired = $row['desired_mode'];
        $locked = self::envLockedToPolling();
        $blockingReason = self::blockingReason($capabilities);

        if ($desired === 'realtime' && $locked) {
            $status = 'degraded';
            $reason = 'config-locked';
        } elseif ($desired === 'realtime' && $blockingReason !== null) {
            $status = 'degraded';
            $reason = $blockingReason;
        } else {
            $status = 'ready';
            $reason = $desired === 'polling' && $locked ? 'config-locked' : null;
        }

        return [
            'desired_mode' => $desired,
            // 兼容既有 API；该字段只表示实际选择，不再派生另一种通信模式。
            'effective_mode' => $desired,
            'status' => $status,
            'revision' => (int) $row['revision'],
            'reason_code' => $reason,
            'checked_at' => $this->stamp($checkedAt),
            'settings' => $settings,
            'last_apply_state' => $row['last_apply_state'] ?? null,
            'last_apply_reason' => $row['last_apply_reason'] ?? null,
            'capabilities' => $capabilities,
        ];
    }

    /** 开启实时前使用提交后的设置检查硬依赖。 */
    public function realtimeBlockingReason(array $settings): ?string
    {
        if (self::envLockedToPolling()) return 'config-locked';
        return self::blockingReason($this->probeCapabilities($settings, $this->clock->nowUtc()));
    }

    private function probeCapabilities(array $settings, \DateTimeImmutable $checkedAt): array
    {
        $capabilities = [];
        foreach ($this->probes as $probe) {
            try {
                $result = $probe->probe($settings);
            } catch (\Throwable $ignored) {
                // probe 失败不允许拖垮快照；统一转 reason_code。
                $result = ['ready' => false, 'provider' => null, 'reason_code' => 'probe-unavailable'];
            }
            $entry = [
                'key' => $probe->key(),
                // 保留三态：null=不适用（not-required），false=不可用；不得用 ?? 吞并。
                'ready' => array_key_exists('ready', $result) ? $result['ready'] : false,
                'provider' => $result['provider'] ?? null,
                'reason_code' => $result['reason_code'] ?? null,
                'note' => $result['note'] ?? null,
                'checked_at' => $this->stamp($checkedAt),
            ];
            $capabilities[] = $entry;
        }
        return $capabilities;
    }

    private static function blockingReason(array $capabilities): ?string
    {
        $byKey = [];
        foreach ($capabilities as $entry) $byKey[$entry['key']] = $entry;
        foreach (self::BLOCKING_KEYS as $key) {
            $entry = $byKey[$key] ?? null;
            if ($entry === null) {
                return 'probe-unavailable';
            }
            if ($entry['ready'] !== true) {
                return (string) ($entry['reason_code'] ?? 'probe-unavailable');
            }
        }
        return null;
    }

    private function stamp(\DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }

    /** 环境锁读取：FPM 走 Env 门面；独立测试进程（未引导容器）回退 getenv。 */
    public static function envLockedToPolling(): bool
    {
        try {
            $value = (string) \think\facade\Env::get('LC_RUNTIME_LOCK', '');
        } catch (\Throwable $ignored) {
            $value = (string) getenv('LC_RUNTIME_LOCK');
        }
        return strtolower(trim($value)) === 'polling';
    }
}
