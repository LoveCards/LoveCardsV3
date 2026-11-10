<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Contract\RuntimeProbe;
use app\modules\Operations\SystemLifecycle\Public\SchemaIdentity;
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
            $schemaIdentity = app(SchemaIdentity::class)->probeIdentity();
        } catch (\Throwable $ignored) {
            return ['ready' => false, 'provider' => $provider, 'reason_code' => 'worker-schema-unavailable'];
        }
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
            $release = $settings['required_release_id'] ?? getenv('LC_ACTIVE_RELEASE_ID');
            $expectedRelease = is_string($release) && $release !== '' ? $release : null;
            $handlers = new HandlerRegistry();
            $requiredQueues = $handlers->queueNamesFor([
                \app\modules\Platform\Eventing\Public\ExecutionMode::REQUEST_SAFE,
                \app\modules\Platform\Eventing\Public\ExecutionMode::DAEMON_REQUIRED,
            ]);
            if (($settings['require_realtime_queue'] ?? true) === false) {
                $requiredQueues = array_values(array_diff($requiredQueues, ['realtime']));
            }
            return self::assess($rows, $handlers->catalogHash(), $expectedRelease, $schemaIdentity, $requiredQueues);
        } catch (\Throwable $ignored) {
            return ['ready' => false, 'provider' => $provider, 'reason_code' => 'probe-unavailable'];
        }
    }

    /** 多个 Worker 中任意一个满足全部条件即可；旧心跳无队列声明时失败关闭。 */
    public static function assess(array $rows, string $catalogHash, ?string $expectedRelease, string $schemaIdentity, array $requiredQueues = ['realtime']): array
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
            $schemaMatches = preg_match('/^[a-f0-9]{64}$/', (string) ($row['schema_version'] ?? '')) === 1
                && hash_equals($schemaIdentity, (string) $row['schema_version']);
            $queues = json_decode((string) ($row['queue_names_json'] ?? ''), true);
            $missingQueues = is_array($queues) ? array_diff($requiredQueues, $queues) : $requiredQueues;
            $coversQueues = is_array($queues) && $missingQueues === [];
            if ($releaseMatches && $catalogMatches && $schemaMatches && $coversQueues) {
                return [
                    'ready' => true,
                    'provider' => $provider,
                    'reason_code' => null,
                    'note' => [
                        'worker_id' => $row['worker_id'] ?? null,
                        'heartbeat_at' => $row['heartbeat_at'] ?? null,
                        'release_id' => $row['release_id'] ?? null,
                        'schema_version' => $row['schema_version'] ?? null,
                    ],
                ];
            }
            $firstReason ??= !$releaseMatches ? 'worker-release-mismatch'
                : (!$catalogMatches ? 'worker-catalog-mismatch'
                    : (!$schemaMatches ? 'worker-schema-mismatch'
                        : (in_array('realtime', $missingQueues, true) ? 'worker-realtime-queue-missing' : 'worker-required-queue-missing')));
        }
        return ['ready' => false, 'provider' => $provider, 'reason_code' => $firstReason ?? 'worker-heartbeat-stale'];
    }
}
