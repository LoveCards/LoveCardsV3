<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Contract\RuntimeConfigStore;
use think\facade\Db;

/** MySQL 单例配置行实现（id=1）。CAS 凭 revision；审计经 $then 同事务执行。 */
final class ThinkOrmRuntimeConfigStore implements RuntimeConfigStore
{
    public function current(): ?array
    {
        try {
            $row = Db::table('runtime_config_state')->where('id', 1)->find();
            if (!is_array($row)) return null;
            $settings = json_decode((string) ($row['settings_json'] ?? ''), false, 512, JSON_THROW_ON_ERROR);
            if (!$settings instanceof \stdClass || !is_string($row['desired_mode'] ?? null)) return null;
            return [
                'desired_mode' => $row['desired_mode'],
                'settings' => (array) $settings,
                'revision' => (int) ($row['revision'] ?? 0),
                'last_apply_state' => $row['last_apply_state'] ?? null,
                'last_apply_reason' => $row['last_apply_reason'] ?? null,
            ];
        } catch (\Throwable $ignored) {
            return null;
        }
    }

    public function selectedModeForEnqueue(): ?string
    {
        $row = Db::table('runtime_config_state')->where('id', 1)->lock(true)->field('desired_mode')->find();
        return is_array($row) ? (string) ($row['desired_mode'] ?? '') : null;
    }

    public function compareAndSwap(int $expectedRevision, string $desiredMode, array $settings, ?int $actorId, ?callable $then = null, ?callable $beforeUpdate = null): bool
    {
        Db::startTrans();
        try {
            // 固定锁序：事件消费策略行先于通信配置行。
            if ($beforeUpdate !== null) $beforeUpdate();
            $affected = Db::table('runtime_config_state')
                ->where('id', 1)
                ->where('revision', $expectedRevision)
                ->update([
                    'desired_mode' => $desiredMode,
                    'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                    'revision' => $expectedRevision + 1,
                    'last_apply_state' => 'applied',
                    'last_apply_reason' => null,
                    'updated_by' => $actorId,
                    'updated_at' => gmdate('Y-m-d H:i:s') . '.' . str_pad((string) ((int) (microtime(true) * 1000000) % 1000000), 6, '0', STR_PAD_LEFT),
                ]);
            if ($affected !== 1) {
                Db::rollback();
                return false;
            }
            if ($then !== null) $then();
            Db::commit();
            return true;
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    public function recordApply(string $state, ?string $reason): void
    {
        try {
            Db::table('runtime_config_state')->where('id', 1)->update([
                'last_apply_state' => $state,
                'last_apply_reason' => $reason,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $ignored) {
            // 失败快照本身不再放大故障。
        }
    }
}
