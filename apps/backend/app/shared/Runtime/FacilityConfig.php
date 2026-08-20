<?php

declare(strict_types=1);

namespace app\shared\Runtime;

final class FacilityConfig
{
    private string $name;
    private string $desiredMode;
    private bool $configured;
    private string $primaryProvider;
    private ?string $fallbackProvider;

    public function __construct(
        string $name,
        string $desiredMode,
        bool $configured,
        string $primaryProvider,
        ?string $fallbackProvider = null
    ) {
        $this->assertIdentifier($name, 'RuntimeFacility');
        $this->assertIdentifier($primaryProvider, 'RuntimeFacility provider');
        if ($fallbackProvider !== null) {
            $this->assertIdentifier($fallbackProvider, 'RuntimeFacility fallback provider');
        }

        $this->name = $name;
        $this->desiredMode = FacilityMode::normalize($desiredMode);
        $this->configured = $configured;
        $this->primaryProvider = $primaryProvider;
        $this->fallbackProvider = $fallbackProvider;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function desiredMode(): string
    {
        return $this->desiredMode;
    }

    public function configured(): bool
    {
        return $this->configured;
    }

    public function primaryProvider(): string
    {
        return $this->primaryProvider;
    }

    public function fallbackProvider(): ?string
    {
        return $this->fallbackProvider;
    }

    private function assertIdentifier(string $value, string $label): void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]*(?:\.[a-z][a-z0-9_-]*)*$/', $value)) {
            throw new \InvalidArgumentException("{$label} 标识不合法：{$value}");
        }
    }
}
