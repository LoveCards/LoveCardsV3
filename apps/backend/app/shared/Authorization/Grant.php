<?php

namespace app\shared\Authorization;

final class Grant
{
    private string $capability;
    private string $scope;

    public function __construct(string $capability, string $scope)
    {
        if ($capability === '') {
            throw new \InvalidArgumentException('Capability cannot be empty');
        }
        Scope::assertValid($scope);
        $this->capability = $capability;
        $this->scope = $scope;
    }

    public function capability(): string { return $this->capability; }
    public function scope(): string { return $this->scope; }

    public function toArray(): array
    {
        return ['capability' => $this->capability, 'scope' => $this->scope];
    }
}
