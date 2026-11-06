<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Contract\RuntimeProbe;
use think\facade\Db;

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
            $stale = max(1, (int) ($settings['scheduler_stale_seconds'] ?? 3600));
            $cutoff = gmdate('Y-m-d H:i:s', time() - $stale);
            $latest = Db::table('scheduler_runs')
                ->where('outcome', '<>', 'running')
                ->order('finished_at', 'desc')
                ->find();
            if (!is_array($latest) || (string) ($latest['finished_at'] ?? '') === '') {
                return ['ready' => false, 'provider' => $provider, 'reason_code' => 'probe-stale', 'note' => ['ran' => false]];
            }
            $fresh = (string) $latest['finished_at'] > $cutoff;
            return [
                'ready' => $fresh,
                'provider' => $provider,
                'reason_code' => $fresh ? null : 'probe-stale',
                'note' => ['finished_at' => $latest['finished_at'], 'outcome' => $latest['outcome'] ?? null, 'jobs_processed' => $latest['jobs_processed'] ?? null],
            ];
        } catch (\Throwable $ignored) {
            return ['ready' => false, 'provider' => $provider, 'reason_code' => 'probe-unavailable'];
        }
    }
}
