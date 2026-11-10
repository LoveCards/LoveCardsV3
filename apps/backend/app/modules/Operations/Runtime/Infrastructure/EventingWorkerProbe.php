<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Contract\RuntimeProbe;
use app\modules\Platform\Eventing\Public\HandlerRegistry;
use think\facade\Db;

/** 实时门禁必须找到实际领取 realtime 队列的当前 Worker。 */
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
            $rows = Db::table('runtime_workers')
                ->where('worker_type', 'eventing')
                ->where('state', 'running')
                ->where('heartbeat_at', '>', $cutoff)
                ->order('heartbeat_at', 'desc')
                ->select()
                ->toArray();
            $release = getenv('LC_ACTIVE_RELEASE_ID');
            $expectedRelease = is_string($release) && $release !== '' ? $release : null;
            return self::assess($rows, (new HandlerRegistry())->catalogHash(), $expectedRelease);
        } catch (\Throwable $ignored) {
            return ['ready' => false, 'provider' => $provider, 'reason_code' => 'probe-unavailable'];
        }
    }

    /** 多个 Worker 中任意一个满足全部条件即可；旧心跳无队列声明时失败关闭。 */
    public static function assess(array $rows, string $catalogHash, ?string $expectedRelease): array
    {
        $provider = 'mysql-runtime_workers';
        if ($rows === []) {
            return ['ready' => false, 'provider' => $provider, 'reason_code' => 'worker-heartbeat-stale'];
        }
        $firstReason = null;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $releaseMatches = $expectedRelease === null
                || hash_equals($expectedRelease, (string) ($row['release_id'] ?? ''));
            $catalogMatches = hash_equals($catalogHash, (string) ($row['handler_catalog_hash'] ?? ''));
            $queues = json_decode((string) ($row['queue_names_json'] ?? ''), true);
            $consumesRealtime = is_array($queues) && in_array('realtime', $queues, true);
            if ($releaseMatches && $catalogMatches && $consumesRealtime) {
                return [
                    'ready' => true,
                    'provider' => $provider,
                    'reason_code' => null,
                    'note' => [
                        'worker_id' => $row['worker_id'] ?? null,
                        'heartbeat_at' => $row['heartbeat_at'] ?? null,
                        'release_id' => $row['release_id'] ?? null,
                    ],
                ];
            }
            $firstReason ??= !$releaseMatches ? 'worker-release-mismatch'
                : (!$catalogMatches ? 'worker-catalog-mismatch' : 'worker-realtime-queue-missing');
        }
        return ['ready' => false, 'provider' => $provider, 'reason_code' => $firstReason ?? 'worker-heartbeat-stale'];
    }
}
