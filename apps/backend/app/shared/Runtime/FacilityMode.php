<?php

declare(strict_types=1);

namespace app\shared\Runtime;

final class FacilityMode
{
    public const OFF = 'off';
    public const AUTO = 'auto';
    public const REQUIRED = 'required';

    public static function normalize(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, self::all(), true)) {
            throw new \InvalidArgumentException("未知 RuntimeFacility 模式：{$mode}");
        }

        return $mode;
    }

    public static function all(): array
    {
        return [self::OFF, self::AUTO, self::REQUIRED];
    }
}
