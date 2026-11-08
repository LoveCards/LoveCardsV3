<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

final class MigrationProgress
{
    private string $id;
    private string $checksum;
    private string $phase;
    private string $status;
    private ?string $cursor;
    private int $revision;
    private int $attempts;
    private ?string $failureCode;

    private function __construct(
        string $id,
        string $checksum,
        string $phase,
        string $status,
        ?string $cursor,
        int $revision,
        int $attempts,
        ?string $failureCode
    ) {
        MigrationPhase::assertValid($phase);
        MigrationStatus::assertValid($status);
        if (!preg_match('/^[0-9]{14}(?:_[a-z0-9][a-z0-9_-]{0,63})?$/', $id)) throw new \InvalidArgumentException('Migration ID 不合法');
        if (!preg_match('/^[a-f0-9]{64}$/', $checksum)) throw new \InvalidArgumentException('Migration Checksum 不合法');
        if ($revision < 0 || $attempts < 0) throw new \InvalidArgumentException('Migration 计数不能为负数');
        if ($cursor !== null && ($phase !== MigrationPhase::BACKFILL || $cursor === '' || strlen($cursor) > 2048)) {
            throw new \InvalidArgumentException('Migration Cursor 不合法');
        }
        if ($status === MigrationStatus::COMPLETE && $phase !== MigrationPhase::COMPLETE) {
            throw new \InvalidArgumentException('完成状态必须对应 complete Phase');
        }
        if ($phase === MigrationPhase::COMPLETE && $status !== MigrationStatus::COMPLETE) {
            throw new \InvalidArgumentException('complete Phase 必须对应完成状态');
        }
        if ($status === MigrationStatus::WAITING && $phase !== MigrationPhase::CONTRACT) {
            throw new \InvalidArgumentException('只有 Contract Phase 可以等待');
        }
        if ($status === MigrationStatus::FAILED && ($failureCode === null || $failureCode === '')) {
            throw new \InvalidArgumentException('失败状态必须包含失败代码');
        }
        if ($status !== MigrationStatus::FAILED && $failureCode !== null) {
            throw new \InvalidArgumentException('非失败状态不能包含失败代码');
        }
        $this->id = $id;
        $this->checksum = $checksum;
        $this->phase = $phase;
        $this->status = $status;
        $this->cursor = $cursor;
        $this->revision = $revision;
        $this->attempts = $attempts;
        $this->failureCode = $failureCode;
    }

    public static function pending(MigrationDescriptor $descriptor): self
    {
        return new self(
            $descriptor->id(),
            $descriptor->checksum(),
            MigrationPhase::PREFLIGHT,
            MigrationStatus::PENDING,
            null,
            0,
            0,
            null
        );
    }

    /** @param array<string, mixed> $data */
    public static function restore(array $data): self
    {
        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['checksum'] ?? ''),
            (string) ($data['phase'] ?? ''),
            (string) ($data['status'] ?? ''),
            isset($data['cursor']) ? (string) $data['cursor'] : null,
            (int) ($data['revision'] ?? -1),
            (int) ($data['attempts'] ?? -1),
            isset($data['failure_code']) ? (string) $data['failure_code'] : null
        );
    }

    public function started(): self
    {
        if ($this->isComplete()) throw new \LogicException('已完成的 Migration 不能重新开始');
        return new self($this->id, $this->checksum, $this->phase, MigrationStatus::RUNNING, $this->cursor, $this->revision, $this->attempts + 1, null);
    }

    public function advance(string $phase, ?string $cursor = null): self
    {
        if ($this->status !== MigrationStatus::RUNNING) throw new \LogicException('只有运行中的 Migration 可以推进');
        return new self($this->id, $this->checksum, $phase, MigrationStatus::PENDING, $cursor, $this->revision, $this->attempts, null);
    }

    public function waitForContract(): self
    {
        if (!in_array($this->status, [MigrationStatus::PENDING, MigrationStatus::RUNNING], true)
            || !in_array($this->phase, [MigrationPhase::VERIFY, MigrationPhase::CONTRACT], true)) {
            throw new \LogicException('当前 Migration 不能等待 Contract');
        }
        return new self($this->id, $this->checksum, MigrationPhase::CONTRACT, MigrationStatus::WAITING, null, $this->revision, $this->attempts, null);
    }

    public function completed(): self
    {
        if ($this->status !== MigrationStatus::RUNNING) throw new \LogicException('只有运行中的 Migration 可以完成');
        return new self($this->id, $this->checksum, MigrationPhase::COMPLETE, MigrationStatus::COMPLETE, null, $this->revision, $this->attempts, null);
    }

    /** 干净安装已导入目标 schema 时记录迁移目录的 checksum，不执行历史 SQL。 */
    public function installedTarget(): self
    {
        if ($this->status !== MigrationStatus::PENDING || $this->attempts !== 0 || $this->cursor !== null) {
            throw new \LogicException('只能为全新目标 Schema 标记未执行的 Migration');
        }
        return new self($this->id, $this->checksum, MigrationPhase::COMPLETE, MigrationStatus::COMPLETE, null, $this->revision, 0, null);
    }

    public function failed(string $failureCode): self
    {
        $failureCode = trim($failureCode);
        if ($this->status !== MigrationStatus::RUNNING || $failureCode === '' || strlen($failureCode) > 128) {
            throw new \LogicException('Migration 失败状态不合法');
        }
        return new self($this->id, $this->checksum, $this->phase, MigrationStatus::FAILED, $this->cursor, $this->revision, $this->attempts, $failureCode);
    }

    /** Explicit operator-only retry transition; schedulers must not call it implicitly. */
    public function retry(): self
    {
        if ($this->status !== MigrationStatus::FAILED) {
            throw new \LogicException('只有失败的 Migration 可以重试');
        }
        return new self($this->id, $this->checksum, $this->phase, MigrationStatus::PENDING, $this->cursor, $this->revision, $this->attempts, null);
    }

    /** 由 MigrationLedger 持久化实现调用。 */
    public function withRevision(int $revision): self
    {
        if ($revision <= $this->revision) throw new \InvalidArgumentException('Migration Revision 必须单调递增');
        return new self($this->id, $this->checksum, $this->phase, $this->status, $this->cursor, $revision, $this->attempts, $this->failureCode);
    }

    public function id(): string { return $this->id; }
    public function checksum(): string { return $this->checksum; }
    public function phase(): string { return $this->phase; }
    public function status(): string { return $this->status; }
    public function cursor(): ?string { return $this->cursor; }
    public function revision(): int { return $this->revision; }
    public function attempts(): int { return $this->attempts; }
    public function failureCode(): ?string { return $this->failureCode; }
    public function isComplete(): bool { return $this->status === MigrationStatus::COMPLETE; }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'checksum' => $this->checksum,
            'phase' => $this->phase,
            'status' => $this->status,
            'cursor' => $this->cursor,
            'revision' => $this->revision,
            'attempts' => $this->attempts,
            'failure_code' => $this->failureCode,
        ];
    }
}
