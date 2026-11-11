<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Contract\RuntimeProbe;
use app\modules\Platform\Eventing\Public\EventConsumption;
use app\modules\Platform\Eventing\Public\ScheduledEventConsumption;

/**
 * Scheduler 能力：scheduler_runs 台账最新完成记录在 stale 窗口内。
 * advisory：不阻塞 realtime 生效，但 UI 展示真实态。
 */
final class SchedulerRunProbe implements RuntimeProbe
{
    public function key(): string
    {
        return 'scheduler';
    }

    public function probe(array $settings): array
    {
        $provider = 'mysql-scheduler_runs';
        try {
            $consumer = (new EventConsumption())->current();
            if ($consumer === null) return ['ready' => false, 'provider' => $provider, 'reason_code' => 'event-consumer-unavailable'];
            if (($consumer['mode'] ?? null) !== 'scheduled') {
                return ['ready' => null, 'provider' => $provider, 'reason_code' => 'not-required'];
            }
            $trigger = (string) ($consumer['scheduled_trigger'] ?? 'none');
            if (!in_array($trigger, ['cli', 'http'], true)) {
                return ['ready' => false, 'provider' => $provider, 'reason_code' => 'scheduler-trigger-unconfigured'];
            }
            $stale = max(1, (int) ($settings['scheduler_stale_seconds'] ?? 3600));
            $cutoff = gmdate('Y-m-d H:i:s', time() - $stale);
            $latest = (new ScheduledEventConsumption())->latestRun();
            $expectedRelease = $settings['required_release_id'] ?? getenv('LC_ACTIVE_RELEASE_ID');
            return self::assessLatest($latest, $trigger, $cutoff, is_string($expectedRelease) ? $expectedRelease : '');
        } catch (\Throwable $ignored) {
            return ['ready' => false, 'provider' => $provider, 'reason_code' => 'probe-unavailable'];
        }
    }
    /** 按实际启动 Release 判断一次性消费者，不能用结束时间代替执行身份。 */
    public static function assessLatest(?array $latest, string $trigger, string $cutoff, string $expectedRelease): array
    {
        $provider = 'mysql-scheduler_runs';
        if (!is_array($latest) || (string) ($latest['finished_at'] ?? '') === '') {
            return ['ready' => false, 'provider' => $provider, 'reason_code' => 'probe-stale', 'note' => ['ran' => false, 'trigger_source' => $trigger]];
        }
        if (($latest['trigger_source'] ?? null) !== $trigger) {
            return ['ready' => false, 'provider' => $provider, 'reason_code' => 'scheduler-trigger-mismatch',
                'note' => ['finished_at' => $latest['finished_at'], 'outcome' => $latest['outcome'] ?? null, 'trigger_source' => $latest['trigger_source'] ?? null]];
        }
        if ($expectedRelease !== '' && !hash_equals($expectedRelease, (string) ($latest['release_id'] ?? ''))) {
            return ['ready' => false, 'provider' => $provider, 'reason_code' => 'scheduler-release-mismatch',
                'note' => ['release_id' => $latest['release_id'] ?? null, 'expected_release_id' => $expectedRelease]];
        }
        $fresh = (string) $latest['finished_at'] > $cutoff;
        $successful = (string) ($latest['outcome'] ?? '') === 'ok';
        return [
            'ready' => $fresh && $successful,
            'provider' => $provider,
            'reason_code' => !$fresh ? 'probe-stale' : ($successful ? null : 'scheduler-run-failed'),
            'note' => ['finished_at' => $latest['finished_at'], 'outcome' => $latest['outcome'] ?? null,
                'jobs_processed' => $latest['jobs_processed'] ?? null, 'trigger_source' => $trigger,
                'release_id' => $latest['release_id'] ?? null],
        ];
    }

}
