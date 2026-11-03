<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

final class EventEnvelope
{
    private string $eventType;
    private int $eventVersion;
    private string $aggregateType;
    private string $aggregateKey;
    private array $payload;
    private \DateTimeImmutable $occurredAt;
    private ?int $actorId;
    private string $correlationId;
    private ?string $causationId;
    private ?string $createdReleaseId;

    public function __construct(
        string $eventType,
        int $eventVersion,
        string $aggregateType,
        string $aggregateKey,
        array $payload,
        \DateTimeImmutable $occurredAt,
        ?int $actorId,
        string $correlationId,
        ?string $causationId = null,
        ?string $createdReleaseId = null
    ) {
        $this->assertName($eventType, 'event_type');
        $this->assertName($aggregateType, 'aggregate_type');
        if ($eventVersion < 1) {
            throw new \InvalidArgumentException('event_version 必须大于 0');
        }
        if ($aggregateKey === '' || strlen($aggregateKey) > 191) {
            throw new \InvalidArgumentException('aggregate_key 长度必须为 1..191');
        }
        if ($actorId !== null && $actorId < 1) {
            throw new \InvalidArgumentException('actor_id 必须为正整数或 null');
        }
        $this->assertTraceId($correlationId, 'correlation_id', false);
        $this->assertTraceId($causationId, 'causation_id', true);
        if ($createdReleaseId !== null && ($createdReleaseId === '' || strlen($createdReleaseId) > 128)) {
            throw new \InvalidArgumentException('created_release_id 长度必须为 1..128 或 null');
        }

        try {
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException('Event payload 无法序列化', 0, $exception);
        }
        if (strlen($encoded) > 65535) {
            throw new \InvalidArgumentException('Event payload 超过 64 KiB 限制');
        }

        $this->eventType = $eventType;
        $this->eventVersion = $eventVersion;
        $this->aggregateType = $aggregateType;
        $this->aggregateKey = $aggregateKey;
        $this->payload = $payload;
        $this->occurredAt = $occurredAt;
        $this->actorId = $actorId;
        $this->correlationId = $correlationId;
        $this->causationId = $causationId;
        $this->createdReleaseId = $createdReleaseId;
    }

    public function eventType(): string { return $this->eventType; }
    public function eventVersion(): int { return $this->eventVersion; }
    public function aggregateType(): string { return $this->aggregateType; }
    public function aggregateKey(): string { return $this->aggregateKey; }
    public function payload(): array { return $this->payload; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function actorId(): ?int { return $this->actorId; }
    public function correlationId(): string { return $this->correlationId; }
    public function causationId(): ?string { return $this->causationId; }
    public function createdReleaseId(): ?string { return $this->createdReleaseId; }

    public function payloadJson(): string
    {
        return json_encode(
            $this->payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    public static function fromPersistence(array $row): self
    {
        try {
            $payload = json_decode((string) ($row['payload'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new \UnexpectedValueException('持久化 Event payload 已损坏', 0, $exception);
        }
        if (!is_array($payload)) {
            throw new \UnexpectedValueException('持久化 Event payload 必须是 JSON object/array');
        }

        return new self(
            (string) ($row['event_type'] ?? ''),
            (int) ($row['event_version'] ?? 0),
            (string) ($row['aggregate_type'] ?? ''),
            (string) ($row['aggregate_key'] ?? ''),
            $payload,
            new \DateTimeImmutable((string) ($row['occurred_at'] ?? 'now'), new \DateTimeZone('UTC')),
            isset($row['actor_id']) ? (int) $row['actor_id'] : null,
            (string) ($row['correlation_id'] ?? ''),
            isset($row['causation_id']) && $row['causation_id'] !== '' ? (string) $row['causation_id'] : null,
            isset($row['created_release_id']) && $row['created_release_id'] !== ''
                ? (string) $row['created_release_id']
                : null
        );
    }

    private function assertName(string $value, string $field): void
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,99}$/', $value)) {
            throw new \InvalidArgumentException("{$field} 不合法");
        }
    }

    private function assertTraceId(?string $value, string $field, bool $nullable): void
    {
        if ($value === null && $nullable) {
            return;
        }
        if ($value === null || $value === '' || strlen($value) > 128) {
            throw new \InvalidArgumentException("{$field} 长度必须为 1..128");
        }
    }
}
