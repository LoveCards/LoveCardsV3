<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Contract\RuntimeProbe;
use think\facade\Db;

/**
 * Event Worker 能力：runtime_workers 存在 eventing 心跳且在 stale 窗口内。
 * realtime 硬依赖之一。
 */
final class EventingWorkerProbe implements RuntimeProbe
{
    public function key(): string
    {
        return 'event-worker';
    }

    public function probe(array $settings): array
    {
        $provider = 'mysql-runtime_workers';
        try {
            $stale = max(1, (int) ($settings['worker_stale_seconds'] ?? 30));
            $cutoff = gmdate('Y-m-d H:i:s', time() - $stale);
            $latest = Db::table('runtime_workers')
                ->where('worker_type', 'eventing')
                ->order('heartbeat_at', 'desc')
                ->find();
            if (!is_array($latest)) {
                return ['ready' => false, 'provider' => $provider, 'reason_code' => 'worker-heartbeat-stale'];
            }
            $fresh = (string) ($latest['heartbeat_at'] ?? '') > $cutoff && (string) ($latest['state'] ?? '') === 'running';
            return [
                'ready' => $fresh,
                'provider' => $provider,
                'reason_code' => $fresh ? null : 'worker-heartbeat-stale',
                'note' => ['worker_id' => $latest['worker_id'] ?? null, 'heartbeat_at' => $latest['heartbeat_at'] ?? null],
            ];
        } catch (\Throwable $ignored) {
            return ['ready' => false, 'provider' => $provider, 'reason_code' => 'probe-unavailable'];
        }
    }
}
