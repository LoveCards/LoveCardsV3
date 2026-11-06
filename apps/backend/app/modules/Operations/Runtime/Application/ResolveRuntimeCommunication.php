<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Application;

use app\modules\Operations\Runtime\Contract\RuntimeClock;
use app\modules\Operations\Runtime\Contract\RuntimeConfigStore;
use app\modules\Operations\Runtime\Contract\RuntimeProbe;
use app\modules\Operations\Runtime\Domain\CommunicationSettings;

/**
 * 通信运行时 Resolver：每次读取经 probe 派生 effective_mode。
 *
 * 硬依赖规则（契约 §4.6）：realtime 生效 ⇔ event-worker 且 realtime-gateway
 * （require_supervisor 时再叠加 supervisor）。Scheduler 为 advisory，不阻塞。
 * desired=polling 时不因 worker/gateway 缺失降级（默认安装 polling/ready）。
 */
final class ResolveRuntimeCommunication
{
    /** realtime 能力判定顺序，第一个失败项决定 reason_code。 */
    private const BLOCKING_KEYS = ['event-worker', 'realtime-gateway', 'supervisor'];

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
        $row = $this->store->current();
        if ($row === null) {
            return [
                'desired_mode' => 'polling',
                'effective_mode' => 'polling',
                'status' => 'degraded',
                'revision' => 0,
                'reason_code' => 'schema-not-ready',
                'checked_at' => $this->stamp($checkedAt),
                'settings' => CommunicationSettings::defaults(),
                'last_apply_state' => null,
                'last_apply_reason' => null,
                'capabilities' => [],
            ];
        }

        $settings = CommunicationSettings::normalize($row['settings']);
        $capabilities = [];
        $byKey = [];
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
            $byKey[$entry['key']] = $entry;
        }

        $desired = in_array($row['desired_mode'], CommunicationSettings::MODES, true) ? $row['desired_mode'] : 'polling';
        $locked = self::envLockedToPolling();

        $blockingReason = null;
        foreach (self::BLOCKING_KEYS as $key) {
            $entry = $byKey[$key] ?? null;
            if ($entry === null) {
                $blockingReason = 'probe-unavailable';
                break;
            }
            // ready=null 表示"不适用"（如 not-required），不参与阻断判定。
            if ($entry['ready'] === false) {
                $blockingReason = (string) ($entry['reason_code'] ?? 'probe-unavailable');
                break;
            }
        }

        if ($desired === 'realtime' && $locked) {
            $effective = 'polling';
            $status = 'degraded';
            $reason = 'config-locked';
        } elseif ($desired === 'realtime' && $blockingReason === null) {
            $effective = 'realtime';
            $status = 'ready';
            $reason = null;
        } elseif ($desired === 'realtime') {
            $effective = 'polling';
            $status = 'degraded';
            $reason = $blockingReason;
        } else {
            $effective = 'polling';
            $status = 'ready';
            $reason = $locked ? 'config-locked' : null;
        }

        return [
            'desired_mode' => $desired,
            'effective_mode' => $effective,
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
