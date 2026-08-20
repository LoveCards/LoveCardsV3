<?php
declare(strict_types=1);

namespace app\shared\Observability;

final class LogContext
{
    private ?string $requestId;
    private ?string $correlationId;
    /** @var array<string, int|float|string|bool|null> */
    private array $attributes;

    /** @param array<string, int|float|string|bool|null> $attributes */
    private function __construct(?string $requestId, ?string $correlationId, array $attributes)
    {
        if ($requestId !== null) self::assertId($requestId, 'Request ID');
        if ($correlationId !== null) self::assertId($correlationId, 'Correlation ID');
        foreach ($attributes as $key => $value) {
            if (!preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', (string) $key)) {
                throw new \InvalidArgumentException('日志上下文字段名不合法');
            }
            if (!is_scalar($value) && $value !== null) {
                throw new \InvalidArgumentException('日志上下文字段值必须是标量');
            }
        }
        unset($attributes['request_id'], $attributes['correlation_id']);
        $this->requestId = $requestId;
        $this->correlationId = $correlationId;
        $this->attributes = $attributes;
    }

    /** @param array<string, int|float|string|bool|null> $attributes */
    public static function start(?string $requestId = null, ?string $correlationId = null, array $attributes = []): self
    {
        $requestId = $requestId ?? bin2hex(random_bytes(16));
        return new self($requestId, $correlationId ?? $requestId, $attributes);
    }

    public static function empty(): self
    {
        return new self(null, null, []);
    }

    /** @param array<string, int|float|string|bool|null> $attributes */
    public function withAttributes(array $attributes): self
    {
        return new self($this->requestId, $this->correlationId, array_merge($this->attributes, $attributes));
    }

    public function requestId(): ?string { return $this->requestId; }
    public function correlationId(): ?string { return $this->correlationId; }

    /** @return array<string, int|float|string|bool|null> */
    public function toArray(): array
    {
        $context = $this->attributes;
        if ($this->requestId !== null) $context['request_id'] = $this->requestId;
        if ($this->correlationId !== null) $context['correlation_id'] = $this->correlationId;
        return $context;
    }

    private static function assertId(string $id, string $field): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/', $id)) {
            throw new \InvalidArgumentException($field . ' 不合法');
        }
    }
}
