<?php

declare(strict_types=1);

namespace app\shared\Runtime;

final class FacilityStatus
{
    private string $name;
    private string $desiredMode;
    private bool $configured;
    private bool $supported;
    private bool $reachable;
    private bool $healthy;
    private ?string $effectiveProvider;
    private ?string $fallbackProvider;
    private string $reasonCode;
    private \DateTimeImmutable $checkedAt;
    private bool $ready;

    public function __construct(
        string $name,
        string $desiredMode,
        bool $configured,
        bool $supported,
        bool $reachable,
        bool $healthy,
        ?string $effectiveProvider,
        ?string $fallbackProvider,
        string $reasonCode,
        \DateTimeImmutable $checkedAt,
        bool $ready
    ) {
        $this->name = $name;
        $this->desiredMode = FacilityMode::normalize($desiredMode);
        $this->configured = $configured;
        $this->supported = $supported;
        $this->reachable = $reachable;
        $this->healthy = $healthy;
        $this->effectiveProvider = $effectiveProvider;
        $this->fallbackProvider = $fallbackProvider;
        $this->reasonCode = $reasonCode;
        $this->checkedAt = $checkedAt;
        $this->ready = $ready;
    }

    public function name(): string { return $this->name; }
    public function desiredMode(): string { return $this->desiredMode; }
    public function configured(): bool { return $this->configured; }
    public function supported(): bool { return $this->supported; }
    public function reachable(): bool { return $this->reachable; }
    public function healthy(): bool { return $this->healthy; }
    public function effectiveProvider(): ?string { return $this->effectiveProvider; }
    public function fallbackProvider(): ?string { return $this->fallbackProvider; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function checkedAt(): \DateTimeImmutable { return $this->checkedAt; }
    public function ready(): bool { return $this->ready; }

    public function enabled(): bool
    {
        return $this->effectiveProvider !== null;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'desired_mode' => $this->desiredMode,
            'configured' => $this->configured,
            'supported' => $this->supported,
            'reachable' => $this->reachable,
            'healthy' => $this->healthy,
            'enabled' => $this->enabled(),
            'effective_provider' => $this->effectiveProvider,
            'fallback_provider' => $this->fallbackProvider,
            'reason_code' => $this->reasonCode,
            'checked_at' => $this->checkedAt->format(DATE_ATOM),
            'ready' => $this->ready,
        ];
    }
}
