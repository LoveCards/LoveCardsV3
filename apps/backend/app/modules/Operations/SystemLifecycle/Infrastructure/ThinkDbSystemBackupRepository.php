<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Contract\SystemBackupRepository;
use app\modules\Operations\SystemLifecycle\Domain\BackupArtifact;
use think\facade\Db;

final class ThinkDbSystemBackupRepository implements SystemBackupRepository
{
    public function reserve(string $backupId, ?string $planId, string $type, ?int $actorId): bool
    {
        Db::startTrans();
        try {
            $existing = Db::table('system_backups')->where('backup_id', $backupId)->lock(true)->find();
            if ($existing !== null && (string) $existing['status'] === 'verified') {
                Db::commit();
                return false;
            }
            $now = date('Y-m-d H:i:s.u');
            if ($existing === null) {
                Db::table('system_backups')->insert([
                    'backup_id' => $backupId, 'plan_id' => $planId, 'backup_type' => $type,
                    'driver' => 'pending', 'location' => '', 'checksum' => null, 'size_bytes' => null,
                    'status' => 'pending', 'metadata_json' => null, 'created_by' => $actorId,
                    'created_at' => $now, 'verified_at' => null, 'restored_at' => null,
                ]);
            } else {
                Db::table('system_backups')->where('backup_id', $backupId)->update([
                    'plan_id' => $planId, 'backup_type' => $type, 'driver' => 'pending', 'location' => '',
                    'checksum' => null, 'size_bytes' => null, 'status' => 'pending', 'metadata_json' => null,
                    'created_by' => $actorId, 'verified_at' => null, 'restored_at' => null,
                ]);
            }
            Db::commit();
            return true;
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    public function complete(BackupArtifact $artifact, ?string $planId, ?int $actorId): void
    {
        $updated = Db::table('system_backups')
            ->where('backup_id', $artifact->backupId())
            ->where('status', 'pending')
            ->update([
                'plan_id' => $planId,
                'driver' => $artifact->driver(),
                'location' => $artifact->location(),
                'checksum' => $artifact->checksum(),
                'size_bytes' => $artifact->sizeBytes(),
                'status' => 'verified',
                'metadata_json' => json_encode($artifact->metadata(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'created_by' => $actorId,
                'verified_at' => date('Y-m-d H:i:s.u'),
            ]);
        if ($updated !== 1) {
            $existing = Db::table('system_backups')->where('backup_id', $artifact->backupId())->find();
            if ($existing === null || (string) ($existing['status'] ?? '') !== 'verified'
                || !hash_equals((string) ($existing['checksum'] ?? ''), $artifact->checksum())) {
                throw new \RuntimeException('Backup 账本记录不存在或已完成');
            }
        }
    }

    public function fail(string $backupId, string $failureCode): void
    {
        Db::table('system_backups')->where('backup_id', $backupId)->update([
            'status' => 'failed',
            'metadata_json' => json_encode(['failure_code' => substr($failureCode, 0, 128)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }

    public function forPlan(string $planId): array
    {
        return Db::table('system_backups')->where('plan_id', $planId)->order('id', 'asc')->select()->toArray();
    }

    public function markRestored(string $backupId): void
    {
        $updated = Db::table('system_backups')->where('backup_id', $backupId)->where('status', 'verified')->update([
            'restored_at' => date('Y-m-d H:i:s.u'),
        ]);
        if ($updated !== 1) throw new \RuntimeException('恢复的备份账本记录不存在');
    }
}
