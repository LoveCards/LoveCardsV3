<?php

namespace app\shared\Authorization;

final class Scope
{
    public const OWN = 'own';
    public const ALL = 'all';
    public const GLOBAL = 'global';

    public static function all(): array
    {
        return [self::OWN, self::ALL, self::GLOBAL];
    }

    public static function assertValid(string $scope): void
    {
        if (!in_array($scope, self::all(), true)) {
            throw new \InvalidArgumentException("Unsupported authorization scope: {$scope}");
        }
    }
}
