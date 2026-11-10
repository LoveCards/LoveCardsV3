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
            if (!is_array($latest) || (string) ($latest['finished_at'] ?? '') === '') {
                return ['ready' => false, 'provider' => $provider, 'reason_code' => 'probe-stale', 'note' => ['ran' => false, 'trigger_source' => $trigger]];
            }
            if (($latest['trigger_source'] ?? null) !== $trigger) {
                return ['ready' => false, 'provider' => $provider, 'reason_code' => 'scheduler-trigger-mismatch',
                    'note' => ['finished_at' => $latest['finished_at'], 'outcome' => $latest['outcome'] ?? null, 'trigger_source' => $latest['trigger_source'] ?? null]];
            }
            $fresh = (string) $latest['finished_at'] > $cutoff;
            $successful = (string) ($latest['outcome'] ?? '') === 'ok';
            return [
                'ready' => $fresh && $successful,
                'provider' => $provider,
                'reason_code' => !$fresh ? 'probe-stale' : ($successful ? null : 'scheduler-run-failed'),
                'note' => ['finished_at' => $latest['finished_at'], 'outcome' => $latest['outcome'] ?? null, 'jobs_processed' => $latest['jobs_processed'] ?? null, 'trigger_source' => $trigger],
            ];
        } catch (\Throwable $ignored) {
            return ['ready' => false, 'provider' => $provider, 'reason_code' => 'probe-unavailable'];
        }
    }
}
