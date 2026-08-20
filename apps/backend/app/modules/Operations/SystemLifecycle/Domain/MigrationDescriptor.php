<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

final class MigrationDescriptor
{
    private string $id;
    private string $description;
    private string $checksum;
    private bool $hasContract;

    public function __construct(string $id, string $description, string $checksum, bool $hasContract = false)
    {
        if (!preg_match('/^[0-9]{14}(?:_[a-z0-9][a-z0-9_-]{0,63})?$/', $id)) {
            throw new \InvalidArgumentException('Migration ID 不合法');
        }
        $description = trim($description);
        if ($description === '' || strlen($description) > 255) {
            throw new \InvalidArgumentException('Migration 描述不合法');
        }
        $checksum = strtolower($checksum);
        if (!preg_match('/^[a-f0-9]{64}$/', $checksum)) {
            throw new \InvalidArgumentException('Migration Checksum 必须是 SHA-256');
        }
        $this->id = $id;
        $this->description = $description;
        $this->checksum = $checksum;
        $this->hasContract = $hasContract;
    }

    public static function fromSource(string $id, string $description, string $source, bool $hasContract = false): self
    {
        return new self($id, $description, hash('sha256', $source), $hasContract);
    }

    public function id(): string { return $this->id; }
    public function description(): string { return $this->description; }
    public function checksum(): string { return $this->checksum; }
    public function hasContract(): bool { return $this->hasContract; }

    /** @return array{id: string, description: string, checksum: string, has_contract: bool} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'checksum' => $this->checksum,
            'has_contract' => $this->hasContract,
        ];
    }
}
