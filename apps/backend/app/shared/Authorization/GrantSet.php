<?php

namespace app\shared\Authorization;

final class GrantSet
{
    /** @var array<string, array<string, true>> */
    private array $grants = [];

    /** @param Grant[] $grants */
    public function __construct(array $grants = [])
    {
        foreach ($grants as $grant) {
            if (!$grant instanceof Grant) {
                throw new \InvalidArgumentException('GrantSet accepts Grant values only');
            }
            $this->grants[$grant->capability()][$grant->scope()] = true;
        }
    }

    /**
     * 迁移期入口：把现有 capability / capability.all 映射为结构化 Grant。
     * 数据库切换到 role_grants 后删除本方法。
     */
    public static function fromRows(array $rows): self
    {
        $grants = [];
        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = method_exists($row, 'toArray') ? $row->toArray() : (array) $row;
            }
            if (!is_array($row)) continue;
            $grants[] = new Grant((string) ($row['capability'] ?? ''), (string) ($row['scope'] ?? ''));
        }
        return new self($grants);
    }

    public function allows(string $capability, string $scope): bool
    {
        Scope::assertValid($scope);
        if (isset($this->grants[$capability][Scope::ALL])) return true;
        return isset($this->grants[$capability][$scope]);
    }

    public function allowsAny(array $requirements): bool
    {
        foreach ($requirements as $requirement) {
            if (!is_array($requirement)) continue;
            if ($this->allows((string) ($requirement['capability'] ?? ''), (string) ($requirement['scope'] ?? ''))) {
                return true;
            }
        }
        return false;
    }

    public function allowsAll(array $requirements): bool
    {
        foreach ($requirements as $requirement) {
            if (!is_array($requirement)) return false;
            if (!$this->allows((string) ($requirement['capability'] ?? ''), (string) ($requirement['scope'] ?? ''))) {
                return false;
            }
        }
        return true;
    }

    public function toArray(): array
    {
        $result = [];
        foreach ($this->grants as $capability => $scopes) {
            foreach (array_keys($scopes) as $scope) {
                $result[] = ['capability' => $capability, 'scope' => $scope];
            }
        }
        return $result;
    }

    /** @return string[] Compatibility view for routes not migrated to access metadata yet. */
    public function capabilityNames(): array
    {
        $result = [];
        foreach ($this->grants as $capability => $scopes) {
            if (isset($scopes[Scope::OWN]) || isset($scopes[Scope::GLOBAL])) {
                $result[] = $capability;
            }
            if (isset($scopes[Scope::ALL])) {
                $result[] = $capability . '.all';
            }
        }
        return $result;
    }
}
