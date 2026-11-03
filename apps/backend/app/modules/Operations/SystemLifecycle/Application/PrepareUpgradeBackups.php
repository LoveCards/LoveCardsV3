<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Application;

use app\modules\Operations\SystemLifecycle\Contract\BackupArtifactStore;
use app\modules\Operations\SystemLifecycle\Contract\SystemBackupRepository;
use app\modules\Operations\SystemLifecycle\Contract\UpgradeBackupGate;
use app\modules\Operations\SystemLifecycle\Domain\BackupArtifact;

final class PrepareUpgradeBackups implements UpgradeBackupGate
{
    public function __construct(
        private BackupArtifactStore $artifacts,
        private SystemBackupRepository $backups
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function prepare(string $planId, ?int $actorId = null): array
    {
        $capabilities = $this->artifacts->capabilities();
        foreach (['database', 'files', 'pointer'] as $type) {
            $capability = $capabilities[$type] ?? null;
            if (!is_array($capability) || ($capability['backup'] ?? false) !== true) {
                $reason = is_array($capability) ? trim((string) ($capability['reason'] ?? '')) : '';
                throw new \RuntimeException('升级前备份能力不可用: ' . $type . ($reason === '' ? '' : '（' . $reason . '）'));
            }
        }
        $result = [];
        foreach (['database', 'files', 'pointer'] as $type) {
            $backupId = $planId . ':' . $type;
            try {
                $shouldCreate = $this->backups->reserve($backupId, $planId, $type, $actorId);
                if (!$shouldCreate) {
                    $verified = array_filter(
                        $this->backups->forPlan($planId),
                        static fn(array $row): bool => ($row['backup_id'] ?? '') === $backupId && ($row['status'] ?? '') === 'verified'
                    );
                    if ($verified !== []) { $result[] = reset($verified); continue; }
                    throw new \RuntimeException('备份账本声称可复用但 Artifact 不存在');
                }
            } catch (\Throwable $exception) {
                // A retry after a process interruption may already have a verified artifact.
                $existing = $this->backups->forPlan($planId);
                $verified = array_filter($existing, static fn(array $row): bool => ($row['backup_type'] ?? '') === $type && ($row['status'] ?? '') === 'verified');
                if ($verified !== []) { $result[] = reset($verified); continue; }
                throw $exception;
            }
            try {
                $artifact = match ($type) {
                    'database' => $this->artifacts->database($backupId),
                    'files' => $this->artifacts->files($backupId),
                    default => $this->artifacts->pointer($backupId),
                };
                $this->artifacts->verify($artifact);
                $this->backups->complete($artifact, $planId, $actorId);
                $result[] = [
                    'backup_id' => $artifact->backupId(), 'backup_type' => $artifact->type(),
                    'driver' => $artifact->driver(), 'checksum' => $artifact->checksum(),
                    'size_bytes' => $artifact->sizeBytes(), 'status' => 'verified',
                ];
            } catch (\Throwable $exception) {
                $this->backups->fail($backupId, get_class($exception));
                throw $exception;
            }
        }
        return $result;
    }

    /**
     * Backwards-compatible application entry point for callers that used the
     * pre-contract name. The public gate is `prepare()`; keeping this wrapper
     * avoids breaking a long-running worker or an extension during rollout.
     *
     * @return array<int, array<string, mixed>>
     */
    public function execute(string $planId, ?int $actorId = null): array
    {
        return $this->prepare($planId, $actorId);
    }

    public function assertReady(string $planId): void
    {
        $rows = $this->backups->forPlan($planId);
        $types = [];
        foreach ($rows as $row) {
            if (($row['status'] ?? '') !== 'verified') continue;
            $type = (string) ($row['backup_type'] ?? '');
            if (isset($types[$type])) continue;
            $artifact = BackupArtifact::restore($row);
            $this->artifacts->verify($artifact);
            $types[$type] = true;
        }
        foreach (['database', 'files', 'pointer'] as $type) {
            if (!isset($types[$type])) throw new \RuntimeException('升级前备份 Gate 未通过: ' . $type);
        }
    }

    public function pointerBackup(string $planId): array
    {
        foreach ($this->backups->forPlan($planId) as $row) {
            if (($row['backup_type'] ?? '') === 'pointer' && ($row['status'] ?? '') === 'verified') {
                $artifact = BackupArtifact::restore($row);
                $this->artifacts->verify($artifact);
                return $row;
            }
        }
        throw new \RuntimeException('升级计划没有可恢复的 Release 指针备份');
    }
}
