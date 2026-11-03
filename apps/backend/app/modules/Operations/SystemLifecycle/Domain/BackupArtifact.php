<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

final class BackupArtifact
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        private string $backupId,
        private string $type,
        private string $driver,
        private string $location,
        private string $checksum,
        private int $sizeBytes,
        private array $metadata = []
    ) {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/', $backupId)) throw new \InvalidArgumentException('Backup ID 不合法');
        if (!in_array($type, ['database', 'files', 'pointer'], true)) throw new \InvalidArgumentException('Backup 类型不合法');
        if ($sizeBytes < 0 || !preg_match('/^[a-f0-9]{64}$/', $checksum)) throw new \InvalidArgumentException('Backup 校验信息不合法');
    }

    /** @param array<string, mixed> $data */
    public static function restore(array $data): self
    {
        $metadata = $data['metadata'] ?? $data['metadata_json'] ?? [];
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }
        return new self(
            (string) ($data['backup_id'] ?? ''),
            (string) ($data['backup_type'] ?? $data['type'] ?? ''),
            (string) ($data['driver'] ?? ''),
            (string) ($data['location'] ?? ''),
            (string) ($data['checksum'] ?? ''),
            (int) ($data['size_bytes'] ?? -1),
            is_array($metadata) ? $metadata : []
        );
    }

    public function backupId(): string { return $this->backupId; }
    public function type(): string { return $this->type; }
    public function driver(): string { return $this->driver; }
    public function location(): string { return $this->location; }
    public function checksum(): string { return $this->checksum; }
    public function sizeBytes(): int { return $this->sizeBytes; }
    /** @return array<string, mixed> */
    public function metadata(): array { return $this->metadata; }
}
