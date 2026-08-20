<?php

declare(strict_types=1);

namespace app\shared\Runtime;

final class FacilityProbeResult
{
    private bool $supported;
    private bool $reachable;
    private bool $healthy;
    private string $reasonCode;

    public function __construct(bool $supported, bool $reachable, bool $healthy, string $reasonCode)
    {
        if ($healthy && (!$supported || !$reachable)) {
            throw new \InvalidArgumentException('healthy 探测结果必须同时 supported 且 reachable');
        }
        if ($reachable && !$supported) {
            throw new \InvalidArgumentException('reachable 探测结果必须同时 supported');
        }
        if (!preg_match('/^[a-z][a-z0-9_.-]*$/', $reasonCode)) {
            throw new \InvalidArgumentException('RuntimeFacility reason_code 不合法');
        }

        $this->supported = $supported;
        $this->reachable = $reachable;
        $this->healthy = $healthy;
        $this->reasonCode = $reasonCode;
    }

    public static function healthy(): self
    {
        return new self(true, true, true, 'healthy');
    }

    public static function unsupported(string $reasonCode = 'unsupported'): self
    {
        return new self(false, false, false, $reasonCode);
    }

    public static function unreachable(string $reasonCode = 'unreachable'): self
    {
        return new self(true, false, false, $reasonCode);
    }

    public static function unhealthy(string $reasonCode = 'unhealthy'): self
    {
        return new self(true, true, false, $reasonCode);
    }

    public function supported(): bool
    {
        return $this->supported;
    }

    public function reachable(): bool
    {
        return $this->reachable;
    }

    public function healthyState(): bool
    {
        return $this->healthy;
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}
