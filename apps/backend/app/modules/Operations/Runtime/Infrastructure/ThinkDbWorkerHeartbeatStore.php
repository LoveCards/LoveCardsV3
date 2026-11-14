<?php

declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Public\WorkerHeartbeat;
use app\modules\Operations\Runtime\Public\WorkerHeartbeatStore;
use think\facade\Db;

final class ThinkDbWorkerHeartbeatStore implements WorkerHeartbeatStore
{
    public function record(WorkerHeartbeat $heartbeat): void
    {
        $values = [
            'worker_type' => $heartbeat->workerType(),
            'state' => $heartbeat->state(),
            'release_id' => $heartbeat->releaseId(),
            'schema_version' => $heartbeat->schemaVersion(),
            'handler_catalog_hash' => $heartbeat->handlerCatalogHash(),
            'queue_names_json' => json_encode($heartbeat->queueNames(), JSON_THROW_ON_ERROR),
            'jobs_processed' => $heartbeat->jobsProcessed(),
            'heartbeat_at' => $heartbeat->heartbeatAt()->format('Y-m-d H:i:s'),
            'updated_at' => $heartbeat->heartbeatAt()->format('Y-m-d H:i:s'),
        ];

        $updated = Db::table('runtime_workers')
            ->where('worker_id', $heartbeat->workerId())
            ->update($values);
        if ($updated > 0) {
            return;
        }

        try {
            Db::table('runtime_workers')->insert(array_merge($values, [
                'worker_id' => $heartbeat->workerId(),
                'created_at' => $heartbeat->heartbeatAt()->format('Y-m-d H:i:s'),
            ]));
        } catch (\Throwable $exception) {
            // 同一 worker_id 并发首次心跳时，唯一键竞争后再执行一次更新。
            $updated = Db::table('runtime_workers')
                ->where('worker_id', $heartbeat->workerId())
                ->update($values);
            if ($updated === 0) {
                throw $exception;
            }
        }
    }
}
